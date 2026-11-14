<?php

namespace Tests\Feature;

use App\Http\Middleware\SwaggerTokenRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    public function test_swagger_ui_is_accessible_and_assets_are_local(): void
    {
        $this->get('/api')->assertOk()->assertSee('id="swagger-spec"', false)
            ->assertSee('Execute')->assertSee('Authorize')->assertSee('/vendor/swagger-ui/swagger-ui-bundle.js')
            ->assertSee('/shared/js/swagger-docs.js')->assertSee('Server response')->assertDontSee('/api/openapi.json');
        $this->get('/api-docs')->assertNotFound();
        $this->get('/api/openapi.json')->assertNotFound();
        $this->assertFileExists(public_path('vendor/swagger-ui/swagger-ui.css'));
        $this->assertFileExists(public_path('vendor/swagger-ui/swagger-ui-bundle.js'));
        $this->assertFileExists(public_path('vendor/swagger-ui/LICENSE'));
    }

    public function test_openapi_documents_every_api_method_with_correct_security_and_parameters(): void
    {
        $spec = $this->get('http://localhost:8888/api')->assertOk()->viewData('spec');
        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('http://localhost:8888/api', $spec['servers'][0]['url']);
        $operations = [];
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')) continue;
            $path = '/'.substr($route->uri(), 4);
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $operation = $spec['paths'][$path][strtolower($method)] ?? null;
                $this->assertIsArray($operation, $method.' '.$path);
                $this->assertStringNotContainsString('Controller@', $operation['summary']);
                $this->assertNotContains($operation['operationId'], $operations);
                $operations[] = $operation['operationId'];
                $protected = in_array('auth:sanctum', $route->gatherMiddleware(), true);
                $this->assertSame($protected ? [['bearerAuth' => []]] : [], $operation['security']);
                preg_match_all('/\{([^}]+)\}/', $path, $matches);
                foreach ($matches[1] as $name) {
                    $parameter = collect($operation['parameters'] ?? [])->firstWhere('name', $name);
                    $this->assertSame('path', $parameter['in']);
                    $this->assertTrue($parameter['required']);
                }
            }
        }
        $documentedCount = array_sum(array_map('count', $spec['paths']));
        $this->assertCount($documentedCount, $operations);
        $this->assertArrayNotHasKey('/openapi.json', $spec['paths']);
        $checkReferences = function ($value) use (&$checkReferences, $spec) {
            if (!is_array($value)) return;
            if (isset($value['$ref'])) {
                $this->assertStringStartsWith('#/components/schemas/', $value['$ref']);
                $this->assertArrayHasKey(substr($value['$ref'], strlen('#/components/schemas/')), $spec['components']['schemas']);
            }
            foreach ($value as $nested) $checkReferences($nested);
        };
        $checkReferences($spec);
    }

    public function test_upload_forms_and_password_confirmation_are_documented(): void
    {
        $paths = $this->get('/api')->assertOk()->viewData('spec')['paths'];
        $upload = $paths['/staff/tickets/{ticket}/status']['post']['requestBody']['content']['multipart/form-data']['schema'];
        $this->assertSame('binary', $upload['properties']['attachments[]']['items']['format']);
        $this->assertContains('note', $upload['required']);
        $this->assertArrayNotHasKey('multipart/form-data', $paths['/staff/tickets/{ticket}/status']['patch']['requestBody']['content']);
        $this->assertSame('binary', $paths['/account/profile']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['avatar']['format']);
        $this->assertContains('password_confirmation', $paths['/auth/register']['post']['requestBody']['content']['application/json']['schema']['required']);
        $this->assertSame(['staff_id', 'scheduled_at', 'note'], $paths['/admin/tickets/{ticket}/assign']['post']['requestBody']['content']['application/json']['schema']['required']);
        foreach (['/account/profile', '/tickets', '/tickets/{ticket}', '/staff/tickets/{ticket}/status'] as $path) {
            $this->assertSame('multipart/form-data', array_key_first($paths[$path]['post']['requestBody']['content']));
            $this->assertArrayHasKey('application/json', $paths[$path]['post']['requestBody']['content']);
        }
        $removeImages = $paths['/tickets/{ticket}']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['remove_attachments'];
        $this->assertSame([0, 1], $removeImages['enum']);
        $this->assertSame(0, $removeImages['default']);
    }

    public function test_swagger_marker_removes_session_and_stateful_detection_only_for_swagger(): void
    {
        $sessionGuards = config('sanctum.guard');
        $headers = ['HTTP_REFERER' => 'http://localhost/api', 'HTTP_ORIGIN' => 'http://localhost', 'HTTP_COOKIE' => 'laravel_session=sample'];
        $request = Request::create('/api/me', 'GET', [], ['laravel_session' => 'sample'], [], $headers + ['HTTP_X_HELPDESK_CLIENT' => 'swagger-ui']);
        (new SwaggerTokenRequests())->handle($request, function ($request) {
            $this->assertSame([], $request->cookies->all());
            $this->assertNull($request->header('cookie'));
            $this->assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend($request));
            $this->assertSame([], config('sanctum.guard'));
            return response()->json(['ok' => true]);
        });
        $this->assertSame($sessionGuards, config('sanctum.guard'));
        $normal = Request::create('/api/me', 'GET', [], ['laravel_session' => 'sample'], [], $headers);
        (new SwaggerTokenRequests())->handle($normal, function ($request) {
            $this->assertSame('sample', $request->cookie('laravel_session'));
            $this->assertSame('http://localhost', $request->header('origin'));
            return response()->json(['ok' => true]);
        });
        $this->withHeaders(['X-Helpdesk-Client' => 'swagger-ui', 'Referer' => 'http://localhost/api'])
            ->getJson('/api/me')->assertUnauthorized();
    }

    public function test_response_documentation_does_not_generate_error_catalogs_or_example_data(): void
    {
        $paths = $this->get('/api')->assertOk()->viewData('spec')['paths'];
        foreach ($paths as $operations) {
            foreach ($operations as $operation) {
                foreach ($operation['responses'] as $code => $response) {
                    $this->assertContains((int) $code, [200, 201]);
                    $this->assertArrayNotHasKey('content', $response);
                }
            }
        }
        $this->assertSame([], $paths['/rooms']['get']['security']);
        $this->assertSame([], $paths['/categories']['get']['security']);
        $this->assertSame([['bearerAuth' => []]], $paths['/admin/dashboard']['get']['security']);
    }
}
