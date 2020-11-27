## About the app
This console app is written in PHP 7.4 and with help of Symfony framework (Symfony 5.1).
 
## Prerequisites
This application requires at least PHP 7.4 installed.
You can easily install PHP with brew on mac osx like this:
```shell script
brew install php
```

To verify that PHP is installed, try: `php -v`, it will ideally show output similar to:

```shell script
PHP 7.4.5 (cli) (built: Apr 23 2020 02:25:56) ( NTS )
Copyright (c) The PHP Group
Zend Engine v3.4.0, Copyright (c) Zend Technologies
    with Zend OPcache v7.4.5, Copyright (c), by Zend Technologies
```

## Installation
To start the project, you need to install application dependencies with help of Composer (PHP's dependency manager).
For your convenience, the executable `composer.phar` is included in the project root directory,
so you can just run the following command to install dependencies:

```shell script
php composer.phar install
```

## Running Tests
Simply run PHPUnit tests from the main directory of the project in the following way: 
```shell script
bin/phpunit
``` 

## Running the application
To run the app over a CSV file with logs (e.g. https://drive.google.com/file/d/1lGlUQyql4esTDBH93mcyytqgHGJSC6ZG/view),
all you need to do is run a command like this:

```shell script
bin/console app:analyze-logs ~/Downloads/datadog_logs.csv
```

where `~/Downloads/datadog_logs.csv` is the path to the log file.

Application accepts few options and full help can be obtained with `bin/console app:analyze-logs --help`.
The main options for the app are:

```shell script
Options:
  -s, --stats-timespan=STATS-TIMESPAN        How many seconds of log lines to consider for stats computation? Default 10. [default: 10]
  -t, --alert-threshold=ALERT-THRESHOLD      How many requests per second on average should trigger an alert? Default 10. [default: 10]
  -w, --alert-time-window=ALERT-TIME-WINDOW  The time expressed as seconds at which logs are taken into account for alerting? Default 120. [default: 120]
```

So, if you wanted to process the file in a way that stats will be rendered for every 60 seconds of logs
and high-traffic alert will be triggered once the traffic exceeds 20 requests per second on average in the last 120 seconds, you would run it like:

```shell script
bin/console app:analyze-logs ~/Downloads/datadog_logs.csv --stats-timespan=60 --alert-threshold=20 --alert-time-window=120  
```

or with help of options shortcuts:

```shell script
bin/console app:analyze-logs ~/Downloads/datadog_logs.csv -s 60 -t 20 -w 120  
```

## Further improvements

As further improvements to the application I would do the following:

* prepare a docker container / environment for running the app locally easily
* write more tests
* if the current performance could/would become a bottleneck on really large log files I could refactor the application a bit to use one queue instead of two - but that potentially at a cost of readability / maintainability
