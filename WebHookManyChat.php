<?php

add_action('latepoint_booking_created', function($booking) {
    // Only proceed if we have a valid booking object
    if (!($booking instanceof OsBookingModel)) {
        return;
    }

    // === 1. CLIENT DATA ===
    $customer         = new OsCustomerModel($booking->customer_id);
    $first_name       = $customer->first_name ?? '';
    $last_name        = $customer->last_name  ?? '';
    $customer_email   = $customer->email      ?? '';
    // Strip non-digits from phone
    $raw_phone        = $customer->phone      ?? '';
    $phone            = preg_replace('/\D+/', '', $raw_phone);

    // === 2. SERVICE DATA ===
    $service          = new OsServiceModel($booking->service_id);
    $service_name     = $service->name ?? '';

    // === 3. FORMATTED DURATION ===
    $duration_minutes = (int) $booking->duration;
    $hours            = floor($duration_minutes / 60);
    $minutes          = $duration_minutes % 60;
    if ($hours > 0 && $minutes > 0) {
        $duration_label = "{$hours} hour" . ($hours > 1 ? 's' : '') . " and {$minutes} minute" . ($minutes > 1 ? 's' : '');
    } elseif ($hours > 0) {
        $duration_label = "{$hours} hour" . ($hours > 1 ? 's' : '');
    } elseif ($minutes > 0) {
        $duration_label = "{$minutes} minute" . ($minutes > 1 ? 's' : '');
    } else {
        $duration_label = "0 minutes";
    }

    // === 4. FORMATTED TOTAL VALUE ===
    $order            = new OsOrderModel($booking->order_item_id);
    $raw_total        = $order->total ?? 0;
    $formatted_total  = 'R$' . number_format($raw_total, 2, ',', '.');

    // === 5. SERVICE EXTRAS ===
    global $wpdb;
    $extras           = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT se.name
             FROM {$wpdb->prefix}latepoint_service_extras AS se
             JOIN {$wpdb->prefix}latepoint_bookings_service_extras AS bse
               ON se.id = bse.service_extra_id
             WHERE bse.booking_id = %d",
            $booking->id
        )
    );
    $extras_label     = !empty($extras)
        ? implode(', ', $extras)
        : 'None';

    // === 6. ATTENDEE COUNT ===
    $attendees             = $booking->total_attendees ?? 1;
    $attendees_label       = (string) $attendees; // always include the number

    // === 7. APPOINTMENT TIME & DATE ===
    $start_minutes  = (int) $booking->start_time;
    $time_label     = sprintf('%02d:%02d', floor($start_minutes / 60), $start_minutes % 60);
    $date_label     = date('d/m/Y', strtotime($booking->start_date));

    // === 8. MANYCHAT CONFIG ===
    $apiKey   = 'API KEY HERE';
    $flowNs   = 'NS FLOW HERE';
    $log_path = ABSPATH . 'wp-content/manychat-full.log';

    // === 9. LOOK UP SUBSCRIBER BY CUSTOM FIELD ===
    $lookupResponse = wp_remote_get(
        'https://api.manychat.com/fb/subscriber/findByCustomField?field_id=13437701&field_value=' . urlencode($phone),
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
        ]
    );
    $subscriber_id = '';
    if (!is_wp_error($lookupResponse)) {
        $body = json_decode(wp_remote_retrieve_body($lookupResponse), true);
        if (!empty($body['data'][0]['id'])) {
            $subscriber_id = $body['data'][0]['id'];
            file_put_contents($log_path, date('Y-m-d H:i:s') . " - Subscriber found: {$subscriber_id}\n", FILE_APPEND);
        } else {
            file_put_contents($log_path, date('Y-m-d H:i:s') . " - Subscriber NOT found\n", FILE_APPEND);
        }
    }

    // === 10. CREATE SUBSCRIBER IF MISSING ===
    if (empty($subscriber_id)) {
        $createResponse = wp_remote_post(
            'https://api.manychat.com/fb/subscriber/createSubscriber',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'whatsapp_phone' => $phone,
                    'first_name'     => $first_name,
                    'last_name'      => $last_name,
                    'custom_fields'  => [
                        'numero_whatsapp' => $phone,
                    ],
                ]),
                'timeout' => 20,
            ]
        );
        if (!is_wp_error($createResponse)) {
            $resp = json_decode(wp_remote_retrieve_body($createResponse), true);
            if (!empty($resp['data']['id'])) {
                $subscriber_id = $resp['data']['id'];
                file_put_contents($log_path, date('Y-m-d H:i:s') . " - Created subscriber_id: {$subscriber_id}\n", FILE_APPEND);
            }
        }
    }

    // === 11. UPDATE CUSTOM FIELDS (ALWAYS, EVEN IF EMPTY) ===
    if ($subscriber_id) {
        $fields = [
            ['field_name'=>'first_name',        'field_value'=>$first_name],
            ['field_name'=>'last_name',         'field_value'=>$last_name],
            ['field_name'=>'numero_whatsapp',   'field_value'=>$phone],
            ['field_name'=>'nome_servico',      'field_value'=>$service_name],
            ['field_name'=>'duracao_servico',   'field_value'=>$duration_label],
            ['field_name'=>'valor_total',       'field_value'=>$formatted_total],
            ['field_name'=>'data_agendamento',  'field_value'=>$date_label],
            ['field_name'=>'hora_agendamento',  'field_value'=>$time_label],
            ['field_name'=>'total_pessoas',     'field_value'=>$attendees_label],
            ['field_name'=>'extras_label',      'field_value'=>$extras_label],
        ];
        $updateResponse = wp_remote_post(
            'https://api.manychat.com/fb/subscriber/setCustomFields',
            [
                'headers'=>[
                    'Authorization'=>'Bearer '.$apiKey,
                    'Content-Type'=>'application/json',
                ],
                'body'=> wp_json_encode([
                    'subscriber_id'=>$subscriber_id,
                    'fields'=>$fields,
                ]),
                'timeout'=>20,
            ]
        );
        file_put_contents($log_path, date('Y-m-d H:i:s') . " - Custom fields updated: ".wp_remote_retrieve_body($updateResponse)."\n", FILE_APPEND);

        // === 12. SEND FLOW TO CLIENT ===
        sleep(5); // ensure fields have propagated
        $sendResponse = wp_remote_post(
            'https://api.manychat.com/fb/sending/sendFlow',
            [
                'headers'=>[
                    'Authorization'=>'Bearer '.$apiKey,
                    'Content-Type'=>'application/json',
                ],
                'body'=> wp_json_encode([
                    'subscriber_id'=>$subscriber_id,
                    'flow_ns'=>$flowNs,
                ]),
                'timeout'=>20,
            ]
        );
        file_put_contents($log_path, date('Y-m-d H:i:s') . " - Flow sent to client: ".wp_remote_retrieve_body($sendResponse)."\n", FILE_APPEND);
    }
}, 50);

?>