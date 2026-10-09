<?php

declare(strict_types=1);

it('serves the application home page', function (): void {
    $this->withoutVite()->get('/')->assertOk();
});
