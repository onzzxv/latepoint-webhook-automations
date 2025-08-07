<?php
add_action('latepoint_booking_created', function($booking) {
    // === Ensure the booking instance is valid ===
    if (!($booking instanceof OsBookingModel)) return;

    // === CUSTOMER INFORMATION ===
    $customer         = new OsCustomerModel($booking->customer_id);
    $first_name       = $customer->first_name ?? '';
    $last_name        = $customer->last_name  ?? '';
    $customer_email   = $customer->email      ?? '';
    $customer_phone   = preg_replace('/\D+/', '', $customer->phone ?? ''); // Digits only

    // === SERVICE DETAILS ===
    $service          = new OsServiceModel($booking->service_id);
    $service_name     = $service->name ?? '';

    // === FORMATTED DURATION (e.g., 1 hour and 30 minutes) ===
    $dur = (int) $booking->duration;
    $h   = floor($dur / 60);
    $m   = $dur % 60;

    if ($h > 0 && $m > 0) {
        $duration_label = "$h hour" . ($h > 1 ? 's' : '') . " and $m minute" . ($m > 1 ? 's' : '');
    } elseif ($h > 0) {
        $duration_label = "$h hour" . ($h > 1 ? 's' : '');
    } elseif ($m > 0) {
        $duration_label = "$m minute" . ($m > 1 ? 's' : '');
    } else {
        $duration_label = "0 minute";
    }

    // === FORMATTED TOTAL PRICE (e.g., R$80,00) ===
    $order       = new OsOrderModel($booking->order_item_id);
    $valor_total = number_format($order->total ?? 0, 2, ',', '.');
    $valor_total = 'R$' . $valor_total;

    // === SERVICE EXTRAS (if selected) ===
    global $wpdb;
    $extras = $wpdb->get_col($wpdb->prepare("
        SELECT se.name
        FROM {$wpdb->prefix}latepoint_service_extras AS se
        JOIN {$wpdb->prefix}latepoint_bookings_service_extras AS bse
          ON se.id = bse.service_extra_id
        WHERE bse.booking_id = %d", $booking->id)
    );
    $extras_label = !empty($extras) ? implode(', ', $extras) : 'None';

    // === TOTAL NUMBER OF PEOPLE (always send as number) ===
    $total_pessoas        = $booking->total_attendees ?? 1;
    $total_pessoas_label  = (string) $total_pessoas;

    // === TIME AND DATE FORMATTING ===
    $minutes     = (int) $booking->start_time;
    $hora_label  = sprintf('%02d:%02d', floor($minutes / 60), $minutes % 60);
    $data_label  = date('d/m/Y', strtotime($booking->start_date));

    // === BOOKING IDENTIFIER ===
    $agendamento_id = '#' . $booking->id;

    // === BUILD JSON PAYLOAD ===
    $payload = [
        'agendamento_id'     => $agendamento_id,
        'first_name'         => $first_name,
        'last_name'          => $last_name,
        'email_cliente'      => $customer_email,
        'telefone_cliente'   => $customer_phone,
        'nome_servico'       => $service_name,
        'duracao_servico'    => $duration_label,
        'valor_total'        => $valor_total,
        'data_agendamento'   => $data_label,
        'hora_agendamento'   => $hora_label,
        'quantidade_pessoas' => $total_pessoas_label,
        'servicos_extras'    => $extras_label,
    ];

    // === SEND DATA TO WEBHOOK RECEIVER (e.g., Make or Zapier) ===
    wp_remote_post(
        'PASTE_YOUR_WEBHOOK_URL_HERE', // Replace with your actual webhook URL
        [
            'method'  => 'POST',
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ]
    );

}, 50);
?>
