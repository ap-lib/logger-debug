<?php declare(strict_types=1);

namespace AP\Logger\Dumper;

use AP\Logger\Action;
use AP\Logger\Level;
use Closure;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Logs messages using PHP's built-in error_log function and to a specified file
 *
 * Supports configurable log level, context printing, stack trace printing,
 * and custom time formatting with an optional timezone. The log messages
 * are written in append mode, ensuring that new logs do not overwrite
 * previous ones.
 *
 * The log format includes timestamps, log levels, and module names,
 * with optional context and backtrace details for debugging purposes.
 *
 * Note: `error_log()` has a system-dependent message length limit.
 * Typically, on Linux, the limit is around 1024 bytes for syslog.
 * If the message exceeds this limit, it may be truncated.
 */
class DebugLog implements AddInterface
{
    readonly public float $start;
    private ?Action       $prev_action = null;

    /**
     * Initializes the FileLog instance with optional configurations
     *
     * @param string $filename
     * @param bool $add_to_error_log
     * @param Level $log_level Minimum log level required for a message to be logged
     * @param bool $print_context Whether to include context data in the log output
     * @param bool $print_trace Whether to include stack trace information in the log output
     * @param string|null $timezone Timezone for formatting log timestamps
     * @param string $date_format Format for displaying timestamps
     * @param ?Closure|string|array $message_decorator Callable to modify the log message output
     *                                     Function signature: function(AP\Logger\Action $action): string
     * @param bool $show_prefix
     * @param float|null $start_microtime
     * @param array|string $session_separator
     */
    public function __construct(
        readonly public string                    $filename = "",
        readonly public bool                      $add_to_error_log = true,
        readonly public Level                     $log_level = Level::INFO,
        readonly public bool                      $print_context = true,
        readonly public bool                      $print_trace = false,
        readonly public ?string                   $timezone = null,
        readonly public string                    $date_format = "Y-m-d H:i:s.u",
        readonly public null|Closure|string|array $message_decorator = null,
        readonly public bool                      $show_prefix = true,
        readonly public string|array              $session_separator = "",
        ?float                                    $start_microtime = null,
    )
    {
        $this->start = is_null($start_microtime)
            ? microtime(true)
            : $start_microtime;
    }

    private function formatTime(float $microtime): string
    {
        $dt = DateTime::createFromFormat(
            'U.u',
            number_format(
                $microtime,
                6,
                '.',
                ''
            )
        );

        if (is_string($this->timezone)) {
            try {
                $dt->setTimeZone(new DateTimeZone($this->timezone));
            } catch (Throwable) {
            }
        }

        return $dt->format($this->date_format);
    }

    /**
     * Logs an action if its level meets or exceeds the configured log level
     *
     * Note: `error_log()` has a system-dependent message length limit.
     * Typically, on Linux, the limit is around 1024 bytes for syslog.
     * If the message exceeds this limit, it may be truncated.
     *
     * @param Action $action The log action to be recorded
     */
    public function add(Action $action): void
    {
        $time  = $this->formatTime($action->microtime);
        $level = $action->level->name;

        $message = is_callable($this->message_decorator)
            ? (string)($this->message_decorator)(
                $action,
                $this->prev_action,
                $this->start
            )
            : $action->message;

        $message = $this->show_prefix
            ? ["$time $action->module::[$level] $message"]
            : [$message];

        if ($this->print_context && count($action->context)) {
            $message[] = "  data:";
            $message[] = substr(print_r($action->context, true), 8, -3);
        }

        if ($this->print_trace) {
            $indent    = str_repeat(" ", 4) . "- ";
            $message[] = "  trace:";
            $message[] =
                $indent . implode("\n$indent",
                    array_map(
                        function ($el) {
                            return ($el['file'] ?? "") . ":" . ($el['line'] ?? "0");
                        },
                        $action->backtrace
                    ),
                ) . "\n";
        }

        if (is_null($this->prev_action) && !empty($this->session_separator)) {
            $message = array_merge(
                is_string($this->session_separator)
                    ? [$this->session_separator]
                    : $this->session_separator,
                $message
            );
        }

        if ($action->level->value >= $this->log_level->value) {
            if (file_exists($this->filename)) {
                file_put_contents(
                    $this->filename,
                    implode("\n", $message) . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }

            if ($this->add_to_error_log) {
                error_log(implode("\n", $message));
            }
        }

        $this->prev_action = $action;
    }
}