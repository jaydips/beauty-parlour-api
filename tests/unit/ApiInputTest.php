<?php

declare(strict_types=1);

use App\Controllers\Api\ApiController;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * ApiController::input() must accept every body the Flutter app sends: JSON, form posts and
 * multipart/form-data (whose raw body is the multipart envelope, never JSON). Regression test for
 * the HTTP 500 on image uploads (IncomingRequest::getJSON() throws on a non-JSON body).
 *
 * @internal
 */
final class ApiInputTest extends CIUnitTestCase
{
    public function testMultipartBodyIsReadFromPostNotParsedAsJson(): void
    {
        $envelope = "--xyz\r\nContent-Disposition: form-data; name=\"title\"\r\n\r\nTest\r\n--xyz--\r\n";
        $request  = $this->request('POST', 'multipart/form-data; boundary=xyz', $envelope, ['title' => 'Test', 'audience' => 'all']);

        $this->assertSame(['title' => 'Test', 'audience' => 'all'], $this->controller($request)->read());
    }

    public function testJsonBodyStillWins(): void
    {
        $request = $this->request('POST', 'application/json', '{"title":"Diwali","days":30}', []);
        $this->assertSame(['title' => 'Diwali', 'days' => 30], $this->controller($request)->read());
    }

    public function testBrokenJsonBodyFallsBackToFormFieldsInsteadOfThrowing(): void
    {
        $request = $this->request('POST', 'application/json', '{not json', ['title' => 'Form']);
        $this->assertSame(['title' => 'Form'], $this->controller($request)->read());

        $empty = $this->request('PUT', 'application/x-www-form-urlencoded', '', []);
        $this->assertSame([], $this->controller($empty)->read());
    }

    private function request(string $method, string $contentType, string $body, array $post): IncomingRequest
    {
        $request = Services::incomingrequest(null, false);
        $request->setMethod($method);
        $request->setHeader('Content-Type', $contentType);
        $request->setBody($body);
        $request->setGlobal('post', $post);

        return $request;
    }

    private function controller(IncomingRequest $request): object
    {
        $controller = new class () extends ApiController {
            public function read(): array
            {
                return $this->input();
            }
        };
        $controller->initController($request, Services::response(null, false), Services::logger());

        return $controller;
    }
}
