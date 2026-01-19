# WWMC Gravity Forms FreeScout Add-On

A WordPress plugin that integrates Gravity Forms with FreeScout helpdesk, creating support conversations directly from form submissions.

## Features

- Creates FreeScout conversations from Gravity Forms submissions
- Maps form fields to FreeScout conversation fields (name, email, subject, message)
- Configurable per-form via Gravity Forms feed settings
- Supports FreeScout API authentication

## Requirements

- WordPress 5.0+
- Gravity Forms 2.5+
- FreeScout instance with API access

## Installation

1. Upload the `wwmc-gf-freescout` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress Plugins menu
3. Configure your FreeScout API settings under Forms → Settings → FreeScout
4. Create a feed for each form you want to connect to FreeScout

## Configuration

### Plugin Settings (Forms → Settings → FreeScout)

- **FreeScout URL**: Your FreeScout instance URL (e.g., `https://support.example.com`)
- **API Key**: FreeScout API key (generate in FreeScout under Manage → API Keys)
- **Mailbox ID**: The mailbox where conversations will be created

### Form Feed Settings

For each form, create a FreeScout feed and map:
- Customer Email (required)
- Customer Name
- Subject
- Message Body

## License

GPL-2.0+
