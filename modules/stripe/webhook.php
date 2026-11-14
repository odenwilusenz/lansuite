<?php

global $request;

$stripe = new \LanSuite\Module\Stripe\StripeClient();
$payload = $request->getContent();
$signature = (string) $request->headers->get('Stripe-Signature', '');
$event = $stripe->constructEvent($payload, $signature);

header('Content-Type: text/plain; charset=UTF-8');

if ($event === null) {
    http_response_code(400);
    echo 'invalid';
    return;
}

if ((bool) $event->livemode !== $stripe->isLive()) {
    http_response_code(400);
    echo 'mode';
    return;
}

$booker = new \LanSuite\Module\Stripe\StripeBooker();

try {
    $claim = $booker->claimEvent((string) $event->id);
    if ($claim === 'done') {
        http_response_code(200);
        echo 'ok';
        return;
    }
    if ($claim === 'busy') {
        http_response_code(500);
        echo 'busy';
        return;
    }

    $booker->handleEvent($event);
    $booker->finishEvent((string) $event->id);
    http_response_code(200);
    echo 'ok';
} catch (\Throwable $e) {
    try {
        $booker->releaseEvent((string) $event->id);
    } catch (\Throwable $ignored) {
    }
    http_response_code(500);
    echo 'error';
}
