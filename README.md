# 📲 LatePoint Webhooks Integration for ManyChat and HTTP Platforms

This project contains two fully functional and production-ready PHP webhook integrations for the [LatePoint](https://latepoint.com) booking plugin for WordPress. The goal is to allow seamless automation with **ManyChat** (via its API) or any third-party automation tool like **Make**, **Zapier**, **Pabbly**, or **n8n** using standard HTTP requests.

## 🚀 Purpose

I created these integrations to streamline booking confirmations and notifications using WhatsApp, email, and other communication tools. I’m not a developer — just a curious enthusiast — and thanks to community support and help from LatePoint (especially with some useful CSS snippets), I was able to put together these robust webhook solutions with the help of AI tools.

This is my way of giving back to the community, hoping it helps others achieve similar integrations with minimal effort.

---

## 📁 Webhook Files

### `WebHookManyChat.php`

> Integration with **ManyChat** API using custom fields and Flow triggers.

**Main features:**
- Automatically creates or finds a subscriber based on WhatsApp number
- Updates **custom fields** like:
  - First/Last Name
  - WhatsApp Number
  - Service Name
  - Service Duration (formatted)
  - Booking Date and Time
  - Total Value (formatted)
  - Number of People
  - Extras selected (or “None”)
- Triggers a custom **Flow** after subscriber data is synced
- Logs all actions to a custom log file for debugging

📌 *Perfect for conversational automation via WhatsApp (using approved templates or open windows).*

---

### `WebHookHTTP.php`

> Integration via **HTTP POST** webhook to Make, Zapier, or other tools.

**Main features:**
- Sends a well-structured JSON payload with:
  - Booking ID
  - Client Info
  - Service Name & Duration
  - Date & Time
  - Total Value
  - Extras selected
  - Number of Attendees
- Easily extensible: add any fields you need
- Compatible with:
  - [Make (Integromat)](https://www.make.com)
  - [Zapier](https://zapier.com)
  - [Pabbly Connect](https://www.pabbly.com/connect/)
  - [n8n](https://n8n.io)

📌 *Ideal for building advanced workflows, like CRM updates, email notifications, and analytics.*

---

## 🛠 How It Works

Both webhook files hook into the `latepoint_booking_created` action fired by LatePoint after a new appointment is booked. From there:

1. Booking and customer details are extracted
2. Extra data is fetched and formatted (like time, price, extras)
3. A payload is assembled
4. Data is sent either:
   - To ManyChat’s API via authenticated HTTP POST (`WebHookManyChat.php`)
   - To your custom webhook URL (`WebHookHTTP.php`)

---

## 🤝 Community Contribution

This integration was built with patience, dedication, and help from:
- The LatePoint team
- AI tools (like ChatGPT)
- Several online examples and experiments

If this helps you or your business, feel free to share improvements or give credit. If you have any questions or need help, I’m happy to support you too. Just open an issue or send a message.

Thank you.
