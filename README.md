# Generate PDFs in Laravel with Puppeteer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/limenet/laravel-pdf.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-pdf)
[![GitHub Tests Action Status](https://github.com/limenet/laravel-pdf/actions/workflows/run-tests.yml/badge.svg)](https://github.com/limenet/laravel-pdf/actions/workflows/run-tests.yml)
[![Fix PHP code style issues](https://github.com/limenet/laravel-pdf/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/limenet/laravel-pdf/actions/workflows/fix-php-code-style-issues.yml)
[![PHPStan](https://github.com/limenet/laravel-pdf/actions/workflows/phpstan.yml/badge.svg)](https://github.com/limenet/laravel-pdf/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/limenet/laravel-pdf.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-pdf)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require limenet/laravel-pdf
```

And set up a scheduled task:

```php
// app/Console/Kernel.php
$schedule->command(\Limenet\LaravelPdf\Commands\Cleanup::class)->hourly();
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-pdf-config"
```

Choose a strategy:
1. Puppeteer. Please also install the Node dependencies:
    ```bash
    npm i puppeteer fs-extra
    ```
2. Browserless.io

For local development, you may want to use the `browserless` strategy with `inline_assets` set to `true`.

## Usage

```php
use Limenet\LaravelPdf\Pdf;
return (new Pdf(
    view: 'hello-world',
))->response();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Linus Metzler](https://github.com/limenet)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
