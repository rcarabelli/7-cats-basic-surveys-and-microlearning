# 7 Cats Basic Surveys and Microlearning

This is a WordPress plugin designed to integrate with Board Support Helpdesk and WhatsApp. It allows you to create and deliver services, microlearning courses, and surveys directly to WhatsApp users via Board Support.

## Features

- Create services, microlearning courses, and surveys within WordPress.
- Deliver content to active WhatsApp users via Board Support.
- Trigger content delivery through WordPress menus.
- Works with the WhatsApp Business API.

## What Does This Plugin Do

This plugin lets you send WhatsApp messages using Board Support as a bridge between WordPress and WhatsApp. The main advantage is that it leverages the standard "WhatsApp" and "WhatsApp Business" accounts without incurring per-message charges like the WhatsApp Business API.

Once installed and running, you can use the WordPress interface to create services and microlearning courses, which can then be sent to active users on WhatsApp via Board Support.

## New Features

- Create menus in WordPress for WhatsApp delivery via Board Support.
- New items in WordPress for creating services and delivering them through a user menu.
- Trigger content delivery through WordPress menus to active users.

## Installation

### Prerequisites

1. **Ensure Board Support is Installed**:
   - Board Support should be installed and using the same database as your WordPress installation. It can be installed as a WordPress plugin or separately using the same database.

### Standard WordPress Plugin Installation

1. **Download the Plugin**:
   - Download the plugin from the provided source.

2. **Unzip the Plugin**:
   - Unzip the downloaded file in your WordPress plugins folder.

3. **Activate the Plugin**:
   - Go to the 'Plugins' screen in WordPress and activate the plugin.

### Post-Installation Configuration

1. **Configure Board Support**:
   - Ensure Board Support is properly configured and using the same database as WordPress.

2. **Create Services and Courses**:
   - Use the new menu items created by the plugin in WordPress to set up services and microlearning courses.

## Usage

### Delivering Content

1. **Create a New Item**:
   - In WordPress, create new items for services, microlearning courses, and surveys.

2. **Trigger Delivery**:
   - Use the menus created by the plugin to send content to active WhatsApp users.

### Active Users

- The plugin works only with active users due to the 24-hour window imposed by the WhatsApp Business API. You must send surveys or microlearning content within this timeframe.

## Setting Up Cron Job

To automate certain actions, you may need to set up a cron job:

1. **Open your crontab file for editing**:
   - Run the command: `crontab -e`.

2. **Add the following line to schedule the command to run every 15 minutes**:
   - `*/15 * * * * /usr/bin/php /path/to/your/wordpress/wp-cron.php`

3. **Save and exit the crontab editor**.

## License

MIT License

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## Contact / More Information

For questions and more information, please contact us via the contact form at [7 Cats Studio](https://www.7catstudio.com) or by writing a ticket to [requests@7catstudio.com](mailto:requests@7catstudio.com).

## Contributing

If you have suggestions for improving this plugin, please open an issue or submit a pull request. Contributions are welcome!
