# laravel-faked
A simple library that fakes out all the core components in the suite of Illuminate packages for Laravel v10+

# Getting Started
>Install from packagist using composer
```bash
$ composer install isocroft/laravel-faked
```

# Use Cases
>This library is typically used in two scenarios:

- **Mocking (Reliably with implementation) for Testing**: It allows a testing framework or package to trick code into using a "fake" or lightweight version of Laravel's router without booting up the entire heavy Laravel framework.

- **Cross-Version Package Compatibility**: It could serve as a polyfill. If a package expects these specific Laravel routing classes to exist but is being run in a non-Laravel environment (or an older version), this prevents the code from throwing a fatal "Class not found" error.
