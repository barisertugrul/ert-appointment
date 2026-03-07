<?php

declare(strict_types=1);

if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/wp-admin-smoke.php <wp-root>\n");
    exit(1);
}

$wpRoot = rtrim($argv[1], "\\/");
$wpLoad = $wpRoot . DIRECTORY_SEPARATOR . 'wp-load.php';
if (!file_exists($wpLoad)) {
    fwrite(STDERR, "wp-load.php not found at: {$wpLoad}\n");
    exit(2);
}

require $wpLoad;

function do_rest(string $method, string $route, array $params = []): WP_REST_Response {
    $req = new WP_REST_Request($method, $route);
    if (!empty($params)) {
        $req->set_body_params($params);
    }
    return rest_do_request($req);
}

$providersRes = do_rest('GET', '/erta/v1/providers');
$providers = rest_get_server()->response_to_data($providersRes, false);
if ($providersRes->get_status() !== 200 || !is_array($providers) || count($providers) === 0) {
    echo 'providers=none_or_error' . PHP_EOL;
    echo 'providers_status=' . $providersRes->get_status() . PHP_EOL;
    exit(3);
}

$providerId = (int) ($providers[0]['id'] ?? 0);
if ($providerId <= 0) {
    echo 'provider_id=invalid' . PHP_EOL;
    exit(4);
}

$slotDatetime = null;
for ($i = 0; $i < 30; $i++) {
    $date = (new DateTimeImmutable('today'))->modify("+{$i} day")->format('Y-m-d');
    $slotsRes = do_rest('GET', "/erta/v1/providers/{$providerId}/slots", ['date' => $date]);
    if ($slotsRes->get_status() !== 200) {
        continue;
    }
    $slotsData = rest_get_server()->response_to_data($slotsRes, false);
    $slots = is_array($slotsData['slots'] ?? null) ? $slotsData['slots'] : [];
    if (!empty($slots) && !empty($slots[0]['datetime'])) {
        $slotDatetime = (string) $slots[0]['datetime'];
        break;
    }
}

if ($slotDatetime === null) {
    echo 'slot=none_fallback=direct_create' . PHP_EOL;
}

$appointmentId = 0;
if ($slotDatetime !== null) {
    $bookingRes = do_rest('POST', '/erta/v1/appointments', [
        'provider_id' => $providerId,
        'start_datetime' => $slotDatetime,
        'customer_name' => 'Smoke Test',
        'customer_email' => 'smoke-test@example.com',
        'customer_phone' => '0000000000',
        'notes' => 'Automated smoke test',
    ]);

    $bookingData = rest_get_server()->response_to_data($bookingRes, false);
    if ($bookingRes->get_status() >= 200 && $bookingRes->get_status() < 300 && is_array($bookingData) && !empty($bookingData['id'])) {
        $appointmentId = (int) $bookingData['id'];
        echo 'booking=ok id=' . $appointmentId . PHP_EOL;
    }
}

if ($appointmentId <= 0) {
    $plugin = \ERTAppointment\Plugin::getInstance(ERTA_FILE);
    $container = $plugin->getContainer();
    $repo = $container->make(\ERTAppointment\Domain\Appointment\AppointmentRepository::class);

    $dto = new \ERTAppointment\Domain\Appointment\BookAppointmentDTO(
        providerId: $providerId,
        departmentId: null,
        formId: null,
        customerUserId: null,
        customerName: 'Smoke Test',
        customerEmail: 'smoke-test@example.com',
        customerPhone: '0000000000',
        startDatetime: new DateTimeImmutable('tomorrow 10:00:00'),
        durationMinutes: 30,
        price: 0.0,
        formData: [],
        notes: 'Automated smoke test',
        arrivalBufferMinutes: 0,
    );

    $appointment = \ERTAppointment\Domain\Appointment\Appointment::create($dto);
    $appointment = $repo->save($appointment);
    $appointmentId = (int) $appointment->id;
    echo 'booking=direct id=' . $appointmentId . PHP_EOL;
}

if ($appointmentId <= 0) {
    echo 'booking=failed' . PHP_EOL;
    exit(6);
}

$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID']]);
if (empty($admins)) {
    echo 'admin_user=missing' . PHP_EOL;
    exit(7);
}

wp_set_current_user((int) $admins[0]->ID);

$confirmRes = do_rest('POST', "/erta/v1/appointments/{$appointmentId}/confirm");
$unconfirmRes = do_rest('POST', "/erta/v1/appointments/{$appointmentId}/unconfirm");
$cancelRes = do_rest('POST', "/erta/v1/appointments/{$appointmentId}/cancel", ['reason' => 'smoke']);

$confirmData = rest_get_server()->response_to_data($confirmRes, false);
$unconfirmData = rest_get_server()->response_to_data($unconfirmRes, false);
$cancelData = rest_get_server()->response_to_data($cancelRes, false);

echo 'confirm_status=' . $confirmRes->get_status() . PHP_EOL;
echo 'unconfirm_status=' . $unconfirmRes->get_status() . PHP_EOL;
echo 'cancel_status=' . $cancelRes->get_status() . PHP_EOL;
if ($confirmRes->get_status() < 200 || $confirmRes->get_status() >= 300) {
    echo 'confirm_error=' . (is_array($confirmData) ? ($confirmData['message'] ?? 'unknown') : 'unknown') . PHP_EOL;
}
if ($unconfirmRes->get_status() < 200 || $unconfirmRes->get_status() >= 300) {
    echo 'unconfirm_error=' . (is_array($unconfirmData) ? ($unconfirmData['message'] ?? 'unknown') : 'unknown') . PHP_EOL;
}
if ($cancelRes->get_status() < 200 || $cancelRes->get_status() >= 300) {
    echo 'cancel_error=' . (is_array($cancelData) ? ($cancelData['message'] ?? 'unknown') : 'unknown') . PHP_EOL;
}

$ok = $confirmRes->get_status() >= 200 && $confirmRes->get_status() < 300
    && $unconfirmRes->get_status() >= 200 && $unconfirmRes->get_status() < 300
    && $cancelRes->get_status() >= 200 && $cancelRes->get_status() < 300;

echo 'lifecycle=' . ($ok ? 'ok' : 'fail') . PHP_EOL;
exit($ok ? 0 : 8);
