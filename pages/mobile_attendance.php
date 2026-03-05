<?php
declare(strict_types=1);

require_login();
$pageTitle = 'Mobile Attendance';

$user = current_user();
$employeeId = (int) ($user['employee_id'] ?? 0);

$hasQrSettingsColumns = table_has_column('scanner_settings', 'qr_secret')
    && table_has_column('scanner_settings', 'allowed_network_prefix')
    && table_has_column('scanner_settings', 'office_latitude')
    && table_has_column('scanner_settings', 'office_longitude')
    && table_has_column('scanner_settings', 'qr_max_distance_meters');

if ($hasQrSettingsColumns) {
    $settings = db()->query(
        'SELECT qr_secret, allowed_network_prefix, office_latitude, office_longitude, qr_max_distance_meters
         FROM scanner_settings
         WHERE id = 1'
    )->fetch();
} else {
    $settings = null;
}

if (!$settings) {
    $settings = array(
        'qr_secret' => 'TEAM_PROJECT_QR',
        'allowed_network_prefix' => '',
        'office_latitude' => null,
        'office_longitude' => null,
        'qr_max_distance_meters' => 20,
    );
}

$qrSecret = (string) ($settings['qr_secret'] ?? 'TEAM_PROJECT_QR');
$allowedPrefix = (string) ($settings['allowed_network_prefix'] ?? '');
$officeLatitude = $settings['office_latitude'] !== null ? (float) $settings['office_latitude'] : null;
$officeLongitude = $settings['office_longitude'] !== null ? (float) $settings['office_longitude'] : null;
$maxDistanceMeters = (int) ($settings['qr_max_distance_meters'] ?? 20);
if ($maxDistanceMeters <= 0) {
    $maxDistanceMeters = 20;
}

$qrCode = trim((string) ($_GET['code'] ?? ''));
$isValidQrCode = $qrCode !== '' && hash_equals($qrSecret, $qrCode);
?>
<div class="d-flex justify-content-between align-items-center page-header">
    <h1 class="h3 mb-0">Mobile QR Attendance</h1>
    <span class="text-muted">Method: QR Code</span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Clock In / Clock Out</h2>
            </div>
            <div class="card-body">
                <?php if ($employeeId <= 0): ?>
                    <div class="alert alert-warning mb-0">Your user is not linked to an employee profile. Contact Admin before using mobile attendance.</div>
                <?php elseif (!$isValidQrCode): ?>
                    <div class="alert alert-warning mb-0">Invalid or missing QR code token. Please scan the official system QR code and try again.</div>
                <?php else: ?>
                    <p class="small text-muted">
                        Use this screen only after scanning the official system QR code. Your attendance is recorded automatically under your own employee ID.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button id="mobileClockInBtn" class="btn btn-success">Clock In</button>
                        <button id="mobileClockOutBtn" class="btn btn-danger">Clock Out</button>
                    </div>
                    <div id="mobileAttendanceStatus" class="alert alert-secondary py-2 mb-0">Waiting for attendance action...</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">QR Rules</h2>
            </div>
            <div class="card-body">
                <?php if (!$hasQrSettingsColumns): ?>
                    <div class="alert alert-warning py-2 small">Database migration is required for Wi-Fi and distance policy settings.</div>
                <?php endif; ?>
                <ul class="mb-0">
                    <li>You must be connected to the same office Wi-Fi network.</li>
                    <li>Your phone must be within <?= h((string) $maxDistanceMeters); ?> meters from the office point.</li>
                    <li>QR attendance records your own user/employee identity automatically.</li>
                </ul>
                <hr>
                <div class="small text-muted">
                    Network prefix: <code><?= h($allowedPrefix !== '' ? $allowedPrefix : 'Not configured'); ?></code><br>
                    Office location: <code><?= h($officeLatitude !== null && $officeLongitude !== null ? $officeLatitude . ', ' . $officeLongitude : 'Not configured'); ?></code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($employeeId > 0 && $isValidQrCode): ?>
<script>
window.mobileAttendanceConfig = {
    clockUrl: "<?= h(url('api/mobile_clock.php')); ?>",
    csrfToken: "<?= h(csrf_token()); ?>",
    qrCode: "<?= h($qrCode); ?>"
};

(function () {
    "use strict";

    var config = window.mobileAttendanceConfig || {};
    var clockInBtn = document.getElementById("mobileClockInBtn");
    var clockOutBtn = document.getElementById("mobileClockOutBtn");
    var statusBox = document.getElementById("mobileAttendanceStatus");

    if (!clockInBtn || !clockOutBtn || !statusBox) {
        return;
    }

    function setStatus(message, type) {
        statusBox.className = "alert py-2 mb-0 alert-" + (type || "secondary");
        statusBox.textContent = message;
    }

    function getPosition() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error("Geolocation is not supported on this device."));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    resolve(position.coords);
                },
                function (error) {
                    reject(new Error(error.message || "Unable to retrieve location."));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0
                }
            );
        });
    }

    async function submit(action) {
        clockInBtn.disabled = true;
        clockOutBtn.disabled = true;
        setStatus("Checking location and submitting attendance...", "info");

        try {
            var coords = await getPosition();
            var response = await fetch(config.clockUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": config.csrfToken
                },
                body: JSON.stringify({
                    action: action,
                    method: "qr",
                    qr_code: config.qrCode,
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                    accuracy: coords.accuracy,
                    csrf_token: config.csrfToken
                })
            });

            var payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || "Unable to submit attendance.");
            }

            setStatus(payload.message, "success");
            window.setTimeout(function () {
                window.location.reload();
            }, 1200);
        } catch (error) {
            setStatus(error.message, "danger");
        } finally {
            clockInBtn.disabled = false;
            clockOutBtn.disabled = false;
        }
    }

    clockInBtn.addEventListener("click", function () {
        submit("in");
    });

    clockOutBtn.addEventListener("click", function () {
        submit("out");
    });
})();
</script>
<?php endif; ?>
