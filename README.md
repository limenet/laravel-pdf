# Generate PDFs in Laravel with Puppeteer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/limenet/laravel-pdf.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-pdf)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/limenet/laravel-pdf/run-tests?label=tests)](https://github.com/limenet/laravel-pdf/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/limenet/laravel-pdf/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/limenet/laravel-pdf/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/limenet/laravel-pdf.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-pdf)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require limenet/laravel-pdf
```

Please also install the Node dependencies:

```bash
npm i puppeteer fs-extra
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
