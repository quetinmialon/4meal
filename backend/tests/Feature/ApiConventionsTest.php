<?php

it('wraps successful api responses in a uniform envelope', function () {
    $response = $this->getJson('/api/_test/success');

    $correlationId = $response->headers->get('X-Correlation-ID');

    expect($correlationId)->not->toBeEmpty();

    $response
        ->assertOk()
        ->assertHeader('X-Correlation-ID', $correlationId)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('meta.correlation_id', $correlationId);
});

it('reuses the incoming correlation id', function () {
    $response = $this->withHeader('X-Correlation-ID', 'test-correlation-id')
        ->getJson('/api/_test/success');

    $response
        ->assertOk()
        ->assertHeader('X-Correlation-ID', 'test-correlation-id')
        ->assertJsonPath('meta.correlation_id', 'test-correlation-id');
});

it('formats validation errors uniformly', function () {
    $response = $this->getJson('/api/_test/validation');

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.message', 'The given data was invalid.')
        ->assertJsonPath('error.details.fields.email.0', 'The email field is required.')
        ->assertJsonStructure([
            'meta' => ['correlation_id'],
        ]);
});

it('formats authentication errors uniformly', function () {
    $response = $this->getJson('/api/_test/authentication');

    $response
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'authentication_error')
        ->assertJsonPath('error.message', 'Authentication is required.')
        ->assertJsonStructure([
            'meta' => ['correlation_id'],
        ]);
});

it('formats authorization errors uniformly', function () {
    $response = $this->getJson('/api/_test/authorization');

    $response
        ->assertStatus(403)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'authorization_error')
        ->assertJsonPath('error.message', 'You are not allowed to perform this action.')
        ->assertJsonStructure([
            'meta' => ['correlation_id'],
        ]);
});

it('formats not found errors uniformly', function () {
    $response = $this->getJson('/api/_test/unknown');

    $response
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'not_found')
        ->assertJsonPath('error.message', 'Resource not found.')
        ->assertJsonStructure([
            'meta' => ['correlation_id'],
        ]);
});

it('formats unexpected errors uniformly', function () {
    $response = $this->getJson('/api/_test/server-error');

    $response
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'server_error')
        ->assertJsonPath('error.message', 'An unexpected error occurred.')
        ->assertJsonStructure([
            'meta' => ['correlation_id'],
        ]);
});

it('formats paginated responses uniformly', function () {
    $response = $this->getJson('/api/_test/paginated');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 3)
        ->assertJsonPath('meta.pagination.current_page', 2)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', 5)
        ->assertJsonPath('meta.pagination.last_page', 3)
        ->assertJsonPath('meta.pagination.from', 3)
        ->assertJsonPath('meta.pagination.to', 4)
        ->assertJsonPath('meta.pagination.has_more_pages', true)
        ->assertJsonStructure([
            'meta' => [
                'correlation_id',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                    'path',
                    'has_more_pages',
                ],
            ],
        ]);
});
