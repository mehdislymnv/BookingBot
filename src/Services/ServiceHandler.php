<?php

namespace Services;

use Bot\TelegramBot;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverWait;
use Monolog\Logger;
use Exceptions\CustomException;

class ServiceHandler
{
    private string $cacheFile;
    private int $cacheTime;
    private Logger $logger;
    private array $chromeOptions;
    private string $host;

    public function __construct(Logger $logger, array $seleniumConfig, array $cacheConfig)
    {
        $this->cacheFile = $cacheConfig['services_cache'];
        $this->cacheTime = $cacheConfig['cache_time'];
        $this->logger = $logger;
        $this->chromeOptions = $seleniumConfig['chrome_options'];
        $this->host = $seleniumConfig['host'];
    }

    public function getServices(bool $forceReload = false): array
    {
        if (!$forceReload && file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile) < $this->cacheTime)) {
            $services = json_decode(file_get_contents($this->cacheFile), true);
            if ($services) {
                return $services;
            }
        }

        $this->logger->info("Starting getServices function...");

        $capabilities = DesiredCapabilities::chrome();
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments($this->chromeOptions);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

        try {
            $driver = RemoteWebDriver::create($this->host, $capabilities);
        } catch (\Exception $e) {
            throw new CustomException("Selenium server ile bağlantı qurula bilmədi: " . $e->getMessage());
        }

        $driver->manage()->window()->maximize();
        $driver->get('https://sandbox.booknetic.com/sandboxes/sandbox-saas-6f49ae724d32a0cf3823/tutor2');
        $this->logger->info("Navigated to URL...");

        $serviceCards = $driver->findElements(WebDriverBy::cssSelector('.booknetic_service_card'));
        $this->logger->info("Found " . count($serviceCards) . " service cards...");

        $services = [];
        foreach ($serviceCards as $serviceCard) {
            $id = $serviceCard->getAttribute('data-id');
            $titleElement = $serviceCard->findElement(WebDriverBy::cssSelector('.booknetic_service_title_span'));
            $priceElement = $serviceCard->findElement(WebDriverBy::cssSelector('.booknetic_service_card_price'));
            if ($titleElement && $priceElement) {
                $title = $titleElement->getText();
                $price = $priceElement->getAttribute('data-price');
                $services[] = [
                    'id' => $id,
                    'title' => $title,
                    'price' => $price,
                ];
                $this->logger->info("Parsed service: $title - $price AZN");
            }
        }

        $driver->quit();
        $this->logger->info("Services parsed: " . json_encode($services));

        file_put_contents($this->cacheFile, json_encode($services));

        return $services;
    }

    public function displayServices(TelegramBot $telegram): void
    {
        $this->logger->info("Starting displayServices function...");
        $services = $this->getServices();
        if (!empty($services)) {
            $buttons = [];
            foreach ($services as $service) {
                $formattedPrice = number_format((float)$service['price'], 2, '.', '');
                if (substr($formattedPrice, -3) === '.00') {
                    $formattedPrice = substr($formattedPrice, 0, -3);
                }
                $buttons[] = [[
                    'text' => $service['title'] . " - " . $formattedPrice . " AZN",
                    'callback_data' => 'service_' . $service['id']
                ]];
            }
            $this->logger->info("Sending inline keyboard with services...");
            $telegram->sendInlineKeyboard("Mövcud servislər:", $buttons);
        } else {
            $this->logger->info("No services found...");
            $telegram->sendMessage("Heç bir servis tapılmadı.");
        }
    }

    public function getServiceById(string $serviceId): ?array
    {
        $this->logger->info("Starting getServiceById function with serviceId: $serviceId...");
        $services = $this->getServices();
        $this->logger->info("Services: " . json_encode($services));
        foreach ($services as $service) {
            if ($service['id'] == $serviceId) {
                $this->logger->info("Service found: " . json_encode($service));
                return $service;
            }
        }
        $this->logger->info("Service not found for serviceId: $serviceId");
        return null;
    }


    private function getAvailableTimes(string $serviceId, string $date): array
    {
        $this->logger->info("Starting getAvailableTimes function for service ID $serviceId and date $date...");
        $host = $this->host;

        $capabilities = DesiredCapabilities::chrome();
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments($this->chromeOptions);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

        $driver = RemoteWebDriver::create($host, $capabilities);
        $driver->manage()->window()->maximize();

        $driver->get('https://sandbox.booknetic.com/sandboxes/sandbox-saas-6f49ae724d32a0cf3823/tutor2');
        $this->logger->info("Navigated to URL...");

        $serviceCard = $driver->findElement(WebDriverBy::cssSelector('.booknetic_service_card[data-id="' . $serviceId . '"]'));
        $serviceCard->click();
        $this->logger->info("Clicked on service card with ID: $serviceId");

        $wait = new WebDriverWait($driver, 10);
        $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_calendar_days')));
        $this->logger->info("Calendar days loaded...");

        try {
            $targetMonth = date('F Y', strtotime($date));
            $currentMonth = $driver->findElement(WebDriverBy::cssSelector('.booknetic_month_name'))->getText();

            while ($currentMonth !== $targetMonth) {
                $nextMonthButton = $driver->findElement(WebDriverBy::cssSelector('.booknetic_next_month'));
                $driver->executeScript("arguments[0].click();", [$nextMonthButton]);
                $this->logger->info("Clicked on next month button");

                $wait->until(WebDriverExpectedCondition::textToBePresentInElement(
                    WebDriverBy::cssSelector('.booknetic_month_name'),
                    $targetMonth
                ));
                $currentMonth = $driver->findElement(WebDriverBy::cssSelector('.booknetic_month_name'))->getText();
                $this->logger->info("Current month is now: $currentMonth");
            }

            $dateSelector = '.booknetic_calendar_days[data-date="' . $date . '"]';
            $this->logger->info("Attempting to find date element with selector: $dateSelector");

            $dateElement = $driver->findElement(WebDriverBy::cssSelector($dateSelector));
            $driver->executeScript("arguments[0].click();", [$dateElement]);
            $this->logger->info("Clicked on date: $date");

            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_next_step_btn')));
            $nextButton = $driver->findElement(WebDriverBy::cssSelector('.booknetic_next_step_btn'));
//            $driver->executeScript("arguments[0].click();", [$nextButton]);
            $this->logger->info("Clicked on next button");

            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_time_element')));
            $this->logger->info("Time elements loaded...");

            $availableTimes = [];
            $timeElements = $driver->findElements(WebDriverBy::cssSelector('.booknetic_time_element'));

            foreach ($timeElements as $timeElement) {
                //                . ' - ' . $timeElement->getAttribute('data-endtime')
                $availableTimes[] = $timeElement->getAttribute('data-time');
            }

            $driver->quit();
            $this->logger->info("Available times:" . json_encode($availableTimes));

            return $availableTimes;
        } catch (NoSuchElementException $e) {
            $this->logger->error("No such element: " . $e->getMessage());
            $driver->quit();
            return [];
        } catch (Exception $e) {
            $this->logger->error("Error: " . $e->getMessage());
            $driver->quit();
            return [];
        }
    }
    public function sendAvailableTimes(TelegramBot $telegram, string $serviceId, string $date): void
    {
        $times = $this->getAvailableTimes($serviceId, $date);
        if (!empty($times)) {
            $buttons = [];
            foreach ($times as $time) {
                $buttons[] = [[
                    'text' => $time,
                    'callback_data' => 'time_' . str_replace(' ', '_', $time)
                ]];
            }
            $telegram->sendInlineKeyboard("Mövcud saatlar:", $buttons);
        } else {
            $telegram->sendMessage("Mövcud saat tapılmadı.");
        }
    }



    public function submitForm(string $serviceId, string $time, string $date, array $userData): void
    {
        $this->logger->info("Starting submitForm function...");
        $host = $this->host;

        $capabilities = DesiredCapabilities::chrome();
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments($this->chromeOptions);
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

        $driver = RemoteWebDriver::create($host, $capabilities);
        $driver->manage()->window()->maximize();

        $driver->get('https://sandbox.booknetic.com/sandboxes/sandbox-saas-6f49ae724d32a0cf3823/tutor2');
        $this->logger->info("Navigated to URL...");

        try {
            $wait = new WebDriverWait($driver, 120);  // Gözləmə müddətini artırdıq

            $this->logger->info("Waiting for service card with ID: $serviceId");
            $serviceCard = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_service_card[data-id="' . $serviceId . '"]'))
            );
            $serviceCard->click();
            $this->logger->info("Clicked on service card with ID: $serviceId");

            $this->logger->info("Waiting for calendar days to load");
            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_calendar_days')));
            $this->logger->info("Calendar days loaded");

            // Select the date
            $this->logger->info("User provided date: $date");
            $targetMonth = date('F Y', strtotime($date));
            $this->logger->info("Formatted target month: $targetMonth");

            $currentMonth = $driver->findElement(WebDriverBy::cssSelector('.booknetic_month_name'))->getText();
            $this->logger->info("Current month: $currentMonth");

            while ($currentMonth !== $targetMonth) {
                $nextMonthButton = $driver->findElement(WebDriverBy::cssSelector('.booknetic_next_month'));
                $driver->executeScript("arguments[0].click();", [$nextMonthButton]);
                $this->logger->info("Clicked on next month button");

                $wait->until(WebDriverExpectedCondition::textToBePresentInElement(
                    WebDriverBy::cssSelector('.booknetic_month_name'),
                    $targetMonth
                ));
                $currentMonth = $driver->findElement(WebDriverBy::cssSelector('.booknetic_month_name'))->getText();
                $this->logger->info("Current month is now: $currentMonth");
            }

            $dateSelector = '.booknetic_calendar_days[data-date="' . $date . '"]';
            $this->logger->info("Attempting to find date element with selector: $dateSelector");

            $dateElement = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($dateSelector))
            );
            $driver->executeScript("arguments[0].click();", [$dateElement]);
            $this->logger->info("Clicked on date: $date");

            $this->logger->info("Waiting for next button to be present");
            $nextButton = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_next_step_btn'))
            );
            $driver->executeScript("arguments[0].click();", [$nextButton]);
            $this->logger->info("Clicked on next button");

            $this->logger->info("Waiting for time elements to load");
            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_time_element')));
            $this->logger->info("Time elements loaded");

            // Select the time
            $timeStart = explode(' - ', $time)[0];
            $timeSelector = '.booknetic_time_element[data-time="' . $timeStart . '"]';
            $this->logger->info("Attempting to find time element with selector: $timeSelector");

            $timeElement = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector($timeSelector))
            );
            $driver->executeScript("arguments[0].click();", [$timeElement]);
            $this->logger->info("Clicked on time: $timeStart");

            $this->logger->info("Waiting for next button to be present again");
            $nextButton = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_next_step_btn'))
            );
            $driver->executeScript("arguments[0].click();", [$nextButton]);
            $this->logger->info("Clicked on next button");

            // Fill in the form
            $this->logger->info("Waiting for form inputs to be present");
            $nameInput = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('input[name="first_name"]'))
            );
            $surnameInput = $driver->findElement(WebDriverBy::cssSelector('input[name="last_name"]'));
            $emailInput = $driver->findElement(WebDriverBy::cssSelector('input[name="email"]'));
            $phoneInput = $driver->findElement(WebDriverBy::cssSelector('input[name="phone"]'));

            $nameInput->sendKeys($userData['name']);
            $surnameInput->sendKeys($userData['surname']);
            $emailInput->sendKeys($userData['email']);
            $phoneInput->sendKeys($userData['phone']);
            $this->logger->info("Form filled with user data");

            // Submit the form
            $this->logger->info("Waiting for submit button to be present");
            $submitButton = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_next_step_btn'))
            );

            $driver->executeScript("arguments[0].click();", [$submitButton]);

            usleep(20000000);
            $this->logger->info("Form submitted 10 saniye gozledi");

            // Wait for the new page to load
            $this->logger->info("Waiting for the new page to load after form submission");
            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_next_step_btn')));

            // Ekran görüntüsünü çəkmək
            $screenshotPath = '/var/www/test/src/user_data/screenshot_after_form.png';  // Faylın yadda saxlanılacağı yer
            $driver->takeScreenshot($screenshotPath);
            $this->logger->info("Screenshot saved at: " . $screenshotPath);

            // Wait for the confirm booking button to be visible and click it
            $this->logger->info("Waiting for confirm booking button to be present");
            $confirmButton = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_confirm_booking_btn'))
            );
            $driver->executeScript("arguments[0].click();", [$confirmButton]);
            $this->logger->info("Clicked on confirm booking button");

            // Ekran görüntüsünü çəkmək
            $screenshotPathhh = '/var/www/test/src/user_data/screenshot_afterrr_form.png';  // Faylın yadda saxlanılacağı yer
            $driver->takeScreenshot($screenshotPathhh);
            $this->logger->info("Screenshot saved at: " . $screenshotPathhh);

            // Elementin mətnini əldə edin
            $buttonText = $confirmButton->getText();
            $this->logger->info("Confirm booking button text: " . $buttonText);

            // Verify success message
            $this->logger->info("Waiting for success message to be present");
            $wait->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.booknetic_appointment_finished_code')));

            $this->logger->info("Attempting to find time element with selector: .booknetic_appointment_finished_code");

            $this->logger->info("Fetching the body tag content...");

            $this->logger->info("Booking confirmed successfully");

        } catch (NoSuchElementException $e) {
            $this->logger->error("No such element: " . $e->getMessage());
        } catch (TimeoutException $e) {
            $this->logger->error("Timeout error: " . $e->getMessage());
        } finally {
            $driver->quit();
        }
    }


}
