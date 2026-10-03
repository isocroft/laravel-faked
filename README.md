# laravel-faked
A simple library that fakes out all the core components in the suite of Illuminate packages for Laravel v10+

## Getting Started
>Install from packagist using composer
```bash
$ composer install isocroft/laravel-faked
```

## Use Cases
>This library is typically used in two scenarios:

- **Mocking (Reliably with implementation) for Testing**: It allows a testing framework or package to trick code into using a "fake" or lightweight version of Laravel's router without booting up the entire heavy Laravel framework.

- **Cross-Version Package Compatibility**: It could serve as a polyfill. If a package expects these specific Laravel routing classes to exist but is being run in a non-Laravel environment (or an older version), this prevents the code from throwing a fatal "Class not found" error.

## Issues To Avoid
>As long as you don't try to load Laravel from before loading the `bootstrap.php` file for this library, none of the issues below will arise.
1. **The "First to Define Wins" Issue**: 
	• If a real Laravel component later tries to load and expects the actual framework component (e.g. `Illuminate\\Routing\Route`), it will be forced to use `LaravelFaked\\Http\\Routing\\FakeRoute` instead, leading to missing method errors (`MethodDoesNotExistException`) in rare cases.

2. **Polluted Global State Issue**: In PHPUnit, by default, all tests run in the same long-lived PHP process. Use the annotations below to avoid polluting the global state.

```php
/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
public function test_fallback_environment()
{
    // All fakes used here will die when this test finishes,
    // leaving the next tests completely clean.
}
```

## Usage
>It is possible to use this package together with Laravel
```php
<?php

namespace Tests\Feature;

use LaravelFaked\Core\Foundation\FakeLaravel;
use LaravelFaked\Core\Foundation\InteractsWithFakeLaravel;

use LaravelFaked\Http\Routing\FakeRoute;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithCachedRoutes;

use App\Http\Controllers\UploadsController;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    use InteractsWithFakeLaravel;
    use WithCachedRoutes;
 
    protected function setUp(): void
    {
        parent::steUp();
        $this->bootFakeLaravel(
            packageConfig: [],
        );
    }

    protected function tearDown(): void
    {
        $this->releaseFakeLaravel();
        parent::tearDown();
    }

    public function test_avatars_can_be_uploaded(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjgxMjQ3LCJlbnYiOiJ0NXN0IiwiaWF0IjoxNjI0ODIwNDU3fQ.sw2ortR6Et01U470t12sB9Btp7023SemuKjh8U1Tn57';

        FakeLaravel::setRequest(FakeRequest::createJson('/avatar', 'POST'));
        $this->withRoute(new FakeRoute('/avatar', [], 'uploads'));
        
        Storage::fake('avatars');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post(action([UploadsController::class, 'store']), [
            'name' => 'face_card',
            'avatar' => $file,
        ], [
            'Content-Type' => 'multipart/form-data',
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertStatus($response, 200);
        Storage::disk('avatars')->assertExists($file->hashName());
    }

}
```
>Here is the controller handling the request.
```php
<?php

/** No need for a Laravel application server to be booted */

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse; // Uses the fake: `LaravelFaked\\Http\\Lifecycle\\FakeJsonRespose`

class UploadsController extends Controller
{
    /**
     * Store a newly created avatar in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(): JsonResponse
    {
        /** @var Request $count */
        $request Request = request(); // Uses the fake: `LaravelFaked\\Http\\Lifecycle\\FakeRequest`
        
        $request->validate([
            'name' => ['required', 'string'],
            'avatar' => ['required', 'image', 'max:2048'],
        ]);
    
        $path = $request->file('avatar')->store('avatars', 'avatars');
    
        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }
}
```

## Package Differences

This package is very different from [illuminate/testing](https://packagist.org/packages/illuminate/testing). This package is used to totally avoid booting up an actual Laravel application and server for use within integration tests. This package bypasses the need for an actual server altogether. You can use it to run integration tests in third-party packages for Laravel where it is not compulsory to boot up an actual Laravel application and server to properly test said third-party package.

## License

Apache-2.0
