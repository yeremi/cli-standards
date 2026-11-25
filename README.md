# CLIStandards PHPCS Standard

PHPCS sniffs for CLI command design quality, currently focused on WP-CLI command code.

This package publishes the `CLIStandards` coding standard as a Packagist-ready Composer package. It enforces 19 checks derived from the CLI Standards model:

- 8 blocking checks reported as errors
- 11 advisory checks reported as warnings

## Installation

```bash
composer require --dev yeremi/cli-standards
```

Requires PHP 7.4 or later and PHP_CodeSniffer 3.13.6+ or 4.x. [PHPCSUtils](https://phpcsutils.com/) is installed alongside as a dependency.

The Composer installer plugin registers the standards automatically. After installation:

```bash
vendor/bin/phpcs -i
```

You should see `CLIStandards` and `CLIStandardsMust` in the installed standards list.

If this standard gates your CI, pin it to patch updates (see [Versioning and upgrades](#versioning-and-upgrades)):

```bash
composer require --dev "yeremi/cli-standards:~1.1.0"
```

## Usage

Run the standard against your CLI command code:

```bash
vendor/bin/phpcs --standard=CLIStandards path/to/cli/
```

Useful variants:

```bash
# Show sniff codes.
vendor/bin/phpcs --standard=CLIStandards -s path/to/cli/

# Enforce only MUST-level checks.
vendor/bin/phpcs --standard=CLIStandards --warning-severity=0 path/to/cli/

# Emit JSON for CI tooling.
vendor/bin/phpcs --standard=CLIStandards --report=json path/to/cli/

# Run only blocking rules.
vendor/bin/phpcs --standard=CLIStandardsMust path/to/cli/
```

`CLIStandardsMust` is intended to behave like `CLIStandards` with warnings suppressed, which makes it useful for staged CI adoption.

## Example Output

Anonymized example from a real local scan:

```text
FILE: sample-plugin/includes/cli/class-command.php
----------------------------------------------------------------------
FOUND 4 ERRORS AND 3 WARNINGS AFFECTING 7 LINES
----------------------------------------------------------------------
  9 | ERROR   | Command name "sample-command" must include a namespace
    |         | (CLIStandards.CommandStructure.CommandRegistration.MissingNamespace)
 24 | ERROR   | Command docblock must contain a "## OPTIONS" section
    |         | (CLIStandards.Documentation.DocblockOptions.MissingOptionsSection)
 25 | ERROR   | Command docblock must contain a "## EXAMPLES" section
    |         | (CLIStandards.Documentation.DocblockExamples.MissingExamplesSection)
 48 | WARNING | Consider using WP_CLI\Utils\get_flag_value()
    |         | (CLIStandards.Arguments.FlagExtraction.DirectAccess)
 77 | WARNING | Bulk loop should use make_progress_bar()
    |         | (CLIStandards.Scalability.ProgressBar.MissingProgressBar)
----------------------------------------------------------------------
```

## What It Enforces

Error-level sniffs:

- `CommandRegistration`
- `FlagNaming`
- `OutputMethod`
- `ErrorSeverity`
- `ConfirmOnDelete`
- `ConfirmPassthrough`
- `DocblockOptions`
- `DocblockExamples`

Warning-level sniffs:

- `FlagExtraction`
- `StdoutStderr`
- `FormatSupport`
- `AvailableFields`
- `WpErrorHandling`
- `DryRunSupport`
- `BatchProcessing`
- `ProgressBar`
- `BatchSizeFlag`
- `SinceAnnotation`
- `DocumentedFlags` (new in 1.2.0; not part of `CLIStandardsMust`)

Every sniff is described in the [sniff reference](docs/sniffs.md): the requirement it implements, why, valid and invalid code, its error codes and properties. The same text is available from PHPCS with `vendor/bin/phpcs --standard=CLIStandards --generator=Markdown` (or `Text`, `HTML`).

Two reports can be fixed with `vendor/bin/phpcbf`, only where the fix cannot change behavior:

- `ConfirmPassthrough`: `WP_CLI::confirm( $message )` gets the assoc args as second argument, when the call is in a command handler or in a function with a `$assoc_args` / `$assocArgs` parameter.
- `OutputMethod`: a standalone `echo`/`print` of one expression whose output ends with a newline (`. PHP_EOL` or a trailing `\n`) becomes `WP_CLI::line()` without that newline. Other `echo`/`print` statements are only reported, since `WP_CLI::line()` would add a newline.

The implementation is intentionally heuristic in some areas. It favors practical signal over deep semantic analysis, so some checks can still produce false negatives or advisory-only warnings.

## Scope

The standard name is generic, but the current implementation is not framework-agnostic. The sniffs are tuned for codebases that expose commands through `WP_CLI`, and most checks activate only when `WP_CLI` usage is detected.

### What counts as a command

Sniffs that check command methods (documentation, safety, output format, batch size) follow WP-CLI's own rules for subcommands. A method is treated as a command handler when:

- it is `public` (or a plain function outside a class), or a closure / arrow function passed directly to `WP_CLI::add_command()`;
- for methods, its class is a command class: it extends or implements a type whose name contains `Command`, has `Command` or `CLI` as a word in its own name, or sits in a file that calls `WP_CLI::add_command()` (helper classes such as fetchers or utilities are skipped);
- it is not a magic method, except `__invoke`;
- it does more than register commands (a method whose only WP-CLI calls are `WP_CLI::add_command()` / `add_hook()` is a bootstrap);
- it uses WP-CLI (`WP_CLI::…`, `WP_CLI\…`) or takes the assoc args as its second parameter (`$assoc_args` or `$assocArgs`).

Documentation can come from the command's docblock or from the arguments of `WP_CLI::add_command()` in the same file: a `synopsis` satisfies `## OPTIONS`, and a `longdesc` containing an `## EXAMPLES` heading satisfies `## EXAMPLES`. For a closure, the docblock is the one placed right before the `WP_CLI::add_command()` statement.

`## OPTIONS` is only required when the command reads its positional or associative arguments (directly or in a same-class helper it calls): a command without arguments has no options to document. Every command still needs a docblock with `## EXAMPLES`.

`DocumentedFlags` compares the flags a command reads with its synopsis, taken from where WP-CLI reads it: the `synopsis` argument of `WP_CLI::add_command()` (array or string), else the docblock's `## OPTIONS` section, else an `## OPTIONS` heading in `longdesc`.

- **UndocumentedFlag**: a flag read in the handler through `$assoc_args['name']`, `get_flag_value( $assoc_args, 'name' )` or `array_key_exists( 'name', $assoc_args )`, but missing from the synopsis. `WP_CLI::confirm( $message, $assoc_args )` reads `--yes`. WP-CLI rejects undocumented flags as unknown once a synopsis exists. Global parameters (`--url`, `--path`, `--user`, ...) and a generic `[--<field>=<value>]` entry are accepted.
- **UnusedFlag**: a documented flag the handler never reads. It is skipped when the handler passes its assoc args on (to a helper, a formatter, `wp_parse_args()`, ...), since those reads cannot be followed.

Flag names are case-sensitive. Commands without a readable synopsis (none, or one built at runtime) are skipped; `DocblockOptions` covers a missing `## OPTIONS`.

Sniffs that depend on what a command does (`ConfirmOnDelete`, `DryRunSupport`, `FormatSupport`, `AvailableFields`, `BatchSizeFlag`) classify it by the name users type: the `@subcommand` annotation, else the name passed to `WP_CLI::add_command()` in the same file, else the method or function name. Keywords must be whole words of that name (`dropdown_stats` is not a "drop" command). `ConfirmOnDelete` and `DryRunSupport` also treat a command as destructive when it calls a content-destroying API such as `wp_delete_post()`, `$wpdb->delete()` or a `$wpdb->query()` with `DELETE`/`TRUNCATE`/`DROP`.

Flag sniffs recognize the assoc args as `$assoc_args`, `$assocArgs`, or the second parameter of a command handler whatever its name.

### Commands that delegate

Thin commands that hand work to helpers are common, so the safety and output checks look beyond the command method:

- **Same-class helpers are followed.** `ConfirmOnDelete` (`WP_CLI::confirm()`), `DryRunSupport` (`dry-run` handling), `FormatSupport` (`format_items()`) and `BatchSizeFlag` (`batch-size` handling) also look inside methods the command calls through `$this->…()`, `self::…()` or `static::…()`, up to two call levels.
- **Other classes cannot be followed** by a static check. Declare the delegation in the command's docblock, with a reason:

```php
/**
 * Wipe tenant data.
 *
 * …
 * @cli-standards confirm-delegated Tenant_Wiper::run() calls WP_CLI::confirm() with $assoc_args
 * @cli-standards dry-run-delegated Tenant_Wiper::run() honors --dry-run
 */
public function wipe( $args, $assoc_args ) {
    $this->wiper->run( $assoc_args );
}
```

Available tags: `confirm-delegated`, `dry-run-delegated`, `format-delegated`. A tag without a reason is ignored, and the report says so. Unlike `phpcs:ignore`, the tag keeps the decision and its reason next to the command, where reviewers see it.

## Configuration

If a support class is still detected as a command class (for example `Acme_CLI_Formatter`), opt it out with a class docblock tag or by name:

```php
/**
 * @cli-standards not-a-command
 */
class Acme_CLI_Formatter { … }
```

```xml
<rule ref="CLIStandards.Documentation.DocblockOptions">
    <properties>
        <property name="ignoreClasses" type="array">
            <element value="Acme\Cli\Legacy_Command"/>
        </property>
    </properties>
</rule>
```

Public methods of a registered command class are subcommands in WP-CLI, so `run()` or `services()` are checked like any other command. If a command class has public lifecycle methods that are never invoked from the command line, exclude them by name (case-insensitive) on any command-aware sniff:

```xml
<rule ref="CLIStandards.Documentation.DocblockOptions">
    <properties>
        <property name="ignoreMethods" type="array">
            <element value="services"/>
        </property>
    </properties>
</rule>
```

The destructive API list used by `ConfirmOnDelete` and `DryRunSupport` can be replaced. Option, meta and transient deletions are not in the default list, because commands use them for housekeeping (locks, caches). Plain names are functions, and names starting with `->` are `$wpdb` methods:

```xml
<rule ref="CLIStandards.Safety.ConfirmOnDelete">
    <properties>
        <property name="destructiveFunctions" type="array">
            <element value="wp_delete_post"/>
            <element value="->delete"/>
            <element value="->query"/>
            <element value="my_plugin_purge_records"/>
        </property>
    </properties>
</rule>
```

`ProgressBar` skips loops over array literals and constants, and loops whose only WP-CLI calls are messages (`error`, `warning`, `line`), unless they walk data from an unbounded source. The list of sources (`get_posts`, `get_users`, `get_results`, `WP_Query`, …) can be replaced:

```xml
<rule ref="CLIStandards.Scalability.ProgressBar">
    <properties>
        <property name="unboundedSources" type="array">
            <element value="get_posts"/>
            <element value="my_plugin_get_records"/>
        </property>
    </properties>
</rule>
```

## Versioning and upgrades

This package follows [Semantic Versioning](https://semver.org/). For a coding standard, the question that matters is whether an update can make your CI fail on code that passed before. The rules are:

| Release | May contain | Can add reports to unchanged code? |
|---------|-------------|:----------------------------------:|
| Patch (`1.1.x`) | Fixes that only remove false positives, crash fixes, documentation | No |
| Minor (`1.x.0`) | New sniffs (as warnings), new ruleset properties, detection fixes that also catch previously missed violations, a higher minimum PHP or PHPCS version | Yes, listed under **May add reports** in the [CHANGELOG](CHANGELOG.md) |
| Major (`x.0.0`) | Renamed or removed sniffs or error codes, severity changes (warning ↔ error), changes to `CLIStandardsMust` | Yes |

Additional commitments:

- New sniffs start as warnings in `CLIStandards`. They only become errors, or join `CLIStandardsMust`, in a major release.
- Raising the minimum PHP or PHPCS version is a minor release, announced under **Requirements** in the CHANGELOG. Composer checks these requirements before installing, so projects on an older environment keep resolving the previous release instead of breaking.
- Error codes (e.g. `CLIStandards.Safety.ConfirmOnDelete.MissingConfirm`) are stable within a major version, so `phpcs:ignore` annotations and ruleset exclusions keep working.

Choosing a constraint:

- `~1.1.0`: patch updates only. Recommended when the standard gates CI; upgrade minors deliberately after reading **May add reports**.
- `^1.1`: all 1.x updates. Fine for advisory runs or when you review new reports as they come.

## Add To `phpcs.xml.dist`

```xml
<?xml version="1.0"?>
<ruleset name="MyPluginStandard">
    <description>Project coding standards.</description>

    <rule ref="CLIStandards"/>

    <file>./includes/cli/</file>
    <exclude-pattern>*/vendor/*</exclude-pattern>
</ruleset>
```

## Development

Install dependencies:

```bash
composer install
```

Run the fixture-based regression suite:

```bash
composer test
```

For contribution workflow and expectations, see `CONTRIBUTING.md`.

## Repository Layout

- `CLIStandards/`: the main standard and sniff classes
- `CLIStandards/Helpers/`: shared base class for command-aware sniffs (command detection, docblock lookup)
- `CLIStandardsMust/`: blocking-only variant for staged adoption
- `tests/Fixtures/`: regression fixtures for expected violations and clean cases
- `scripts/verify-fixtures.php`: assertion-based test runner used by CI

## License

See LICENSE ([MIT](LICENSE)).
