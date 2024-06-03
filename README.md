## README

Video link

https://www.youtube.com/watch?si=ueu8dCzrotAp-MYc&v=qUpkgnrOdgA&feature=youtu.be


# Qeyd:
Hal-hazırda işlədiyim üçün və vaxt məhdudiyyətinə görə tapşırıqda bəzi boşluqlar var. Ən əsası təkminləşdirilməlidir.

# Reservation Bot with PHP and Selenium

This project is a Telegram bot for handling reservations. It leverages PHP for the bot logic and Selenium for web scraping and interactions with the reservation website. The bot communicates with users to select services, dates, and times, and then confirms the reservation.

### Prerequisites

- PHP (version ^7.4 || ^8.0)
- Composer
- Selenium Server
- Java
- Web browser (Chrome)

### Project Structure

```
/src
  /Bot
    /TelegramBot.php
  /Helpers
    /Functions.php
    /UserDataHandler.php
  /Services
    /ServiceHandler.php
  /Exceptions
    /CustomException.php
/public
  index.php
/vendor
  (Composer dependencies)
composer.json
.env
```

### Installation

1. **Clone the Repository**

   ```bash
   git clone <repository_url>
   cd <repository_directory>
   ```

2. **Install Dependencies**

   Run the following command in the project root to install PHP dependencies:

   ```bash
   composer install
   ```

3. **Configure Environment Variables**

   Create a `.env` file in the project root directory and add the necessary environment variables:

   ```
   TELEGRAM_BOT_TOKEN=your_telegram_bot_token
   WEBHOOK_URL=https://site_url/telegram
   ```

### Running the Project

1. **Start the Selenium Server**

   Download the Selenium server jar file and run the following command:

   ```bash
   sudo java -jar /path/to/selenium-server-4.21.0.jar standalone --selenium-manager true
   ```

2. **Run the PHP Built-in Server**

   In the project root directory, run:

   ```bash
   php -S localhost:8081 -t public
   ```

### Usage

- The bot interacts with users via Telegram to select services, dates, and times for reservations.
- The bot uses Selenium to scrape available services and timings from the reservation website.

### Example Commands

- **Start the Bot**

  Users can start interacting with the bot by sending `/start` command in Telegram.

### Packages Used

- `guzzlehttp/guzzle`: HTTP client for making requests.
- `voku/simple_html_dom`: Library for handling HTML DOM parsing.
- `php-webdriver/webdriver`: PHP WebDriver for Selenium.
- `vlucas/phpdotenv`: Library to load environment variables.
- `monolog/monolog`: Logging library.

### Autoloading

The project uses PSR-4 autoloading standard. The namespaces and directories are defined in `composer.json`:

```json
"autoload": {
  "psr-4": {
    "Bot\\": "src/Bot/",
    "Helpers\\": "src/Helpers/",
    "Services\\": "src/Services/",
    "Exceptions\\": "src/Exceptions/"
  }
}
```

### Functions Overview

- **getServices($forceReload = false)**: Fetches available services either from cache or by scraping the website.
- **displayServices($telegram)**: Sends the list of available services to the user via Telegram.
- **getServiceById($serviceId)**: Retrieves service details by ID.
- **getAvailableDates($serviceId)**: Scrapes available dates for a selected service.
- **getAvailableTimes($serviceId, $date)**: Scrapes available times for a selected service and date.
- **sendAvailableTimes($telegram, $serviceId, $date)**: Sends available times to the user via Telegram.

### Troubleshooting

- Ensure the Selenium server is running and accessible at the configured URL.
- Verify the Telegram bot token is correct and the bot is properly set up with the Telegram API.
- Check for any errors in the PHP server logs and Selenium logs for troubleshooting.

### Author

- Name: mxd
- Email: mehdislymnv@gmail.com

