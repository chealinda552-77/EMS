(function () {
  "use strict";

  var config = window.attendanceConfig;
  if (!config) {
    return;
  }

  var scanBtn = document.getElementById("scanBtn");
  var clockInBtn = document.getElementById("clockInBtn");
  var clockOutBtn = document.getElementById("clockOutBtn");
  var fingerprintInput = document.getElementById("fingerprintInput");
  var methodInput = document.getElementById("fingerprintMethod");
  var statusBox = document.getElementById("attendanceStatus");

  if (!fingerprintInput || !methodInput || !statusBox) {
    return;
  }

  var currentMethod = config.mode || "manual";

  function setStatus(message, type) {
    statusBox.className = "alert py-2 mb-0 alert-" + (type || "secondary");
    statusBox.textContent = message;
  }

  async function verifyWebAuthn() {
    if (
      !window.PublicKeyCredential ||
      !navigator.credentials ||
      !window.crypto
    ) {
      return false;
    }

    try {
      var challenge = window.crypto.getRandomValues(new Uint8Array(32));
      var credential = await navigator.credentials.get({
        publicKey: {
          challenge: challenge,
          timeout: 12000,
          userVerification: "required",
        },
      });

      return !!credential;
    } catch (error) {
      return false;
    }
  }

  async function readFromApi() {
    var response = await fetch(config.scanProxyUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": config.csrfToken,
      },
      body: JSON.stringify({ csrf_token: config.csrfToken }),
    });

    var payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Scanner API failed.");
    }

    return payload.fingerprint_id;
  }

  async function scanFingerprint() {
    var fingerprintId = "";
    currentMethod = config.mode || "manual";

    if (config.mode === "api") {
      setStatus("Requesting scanner service...", "info");
      try {
        fingerprintId = await readFromApi();
        currentMethod = "api";
        setStatus("Fingerprint captured from scanner API.", "success");
      } catch (error) {
        currentMethod = "manual";
        setStatus("API scan failed, switching to manual entry.", "warning");
      }
    }

    if (config.mode === "webauthn") {
      setStatus("Requesting biometric verification...", "info");
      var verified = await verifyWebAuthn();
      if (verified) {
        currentMethod = "webauthn";
        setStatus("Biometric verification successful.", "success");
      } else {
        currentMethod = "manual";
        setStatus(
          "Biometric verification unavailable, manual entry required.",
          "warning",
        );
      }
    }

    if (config.mode === "thumb") {
      currentMethod = "thumb";
      setStatus(
        "Thumb scan mode enabled. Capture thumb ID to continue.",
        "info",
      );
    }

    if (!fingerprintId) {
      var promptLabel =
        config.mode === "thumb"
          ? "Enter scanned thumb ID:"
          : "Enter scanned fingerprint ID:";
      var promptValue = window.prompt(
        promptLabel,
        fingerprintInput.value || "",
      );
      if (!promptValue) {
        setStatus("Scan cancelled.", "secondary");
        return;
      }
      fingerprintId = promptValue.trim();
    }

    fingerprintInput.value = fingerprintId;
    methodInput.value = currentMethod;
  }

  async function submitAttendance(action) {
    var fingerprintId = (fingerprintInput.value || "").trim();
    if (!fingerprintId) {
      setStatus("Fingerprint ID is required.", "warning");
      return;
    }

    setStatus("Submitting attendance...", "info");

    try {
      var response = await fetch(config.clockUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": config.csrfToken,
        },
        body: JSON.stringify({
          action: action,
          fingerprint_id: fingerprintId,
          method: currentMethod,
          csrf_token: config.csrfToken,
        }),
      });

      var payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || "Unable to save attendance.");
      }

      setStatus(payload.employee + " - " + payload.message, "success");
      window.setTimeout(function () {
        window.location.reload();
      }, 1000);
    } catch (error) {
      setStatus(error.message, "danger");
    }
  }

  if (scanBtn) {
    scanBtn.addEventListener("click", function () {
      scanFingerprint();
    });
  }

  if (clockInBtn) {
    clockInBtn.addEventListener("click", function () {
      submitAttendance("in");
    });
  }

  if (clockOutBtn) {
    clockOutBtn.addEventListener("click", function () {
      submitAttendance("out");
    });
  }
})();
