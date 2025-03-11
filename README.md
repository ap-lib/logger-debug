# AP\Logger

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)


`DebugLog` is a simple and configurable logging utility for PHP that writes logs both to a specified file and PHP's built-in `error_log()` function. It supports log levels, context printing, stack traces, and customizable message formatting.

## Installation

```bash
composer require ap-lib/logger-debug
```

## Features

- Logs messages to a file and `error_log()`
- Supports configurable log levels
- Optionally prints context and stack traces for debugging
- Customizable timestamp format with timezone support
- Allows message formatting through a decorator function
- Ensures logs are written in append mode to preserve history

## Requirements

- PHP 8.3 or higher

## Getting started

```php
use AP\Logger\Dumper\DebugLog;

Log::router()->setDefaultDumper(new DebugLog(
    filename: "/logs/default.log",
    log_level: Level::DEBUG,
    print_context: true,
    print_trace: false,
    timezone: "pst"
));
```