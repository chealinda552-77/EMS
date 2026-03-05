(function () {
  "use strict";

  var config = window.attendanceConfig;
  if (!config) {
    return;
  }

  var scanBtn = document.getElementById("scanBtn");
  var clockInBtn = document.getElementById("clockInBtn");
  var clockOutBtn = document.getElementById("clockOutBtn");
  var scanIdInput = document.getElementById("fingerprintInput");
  var methodInput = document.getElementById("fingerprintMethod");
  var statusBox = document.getElementById("attendanceStatus");

  if (!scanIdInput || !methodInput || !statusBox) {
    return;
  }

  var methodLabels = {
    fingerprint: "Fingerprint",
    card: "Card",
    face: "Face Recognition",
    qr: "QR Code",
  };
  var currentMethod = "fingerprint";

  function setStatus(message, type) {
    statusBox.className = "alert py-2 mb-0 alert-" + (type || "secondary");
    statusBox.textContent = message;
  }

  function getSelectedMethod() {
    var method = (methodInput.value || "").toLowerCase();
    if (!methodLabels[method]) {
      return "fingerprint";
    }
    return method;
  }

  function updateMethodUi() {
    var method = getSelectedMethod();
    var methodLabel = methodLabels[method];
    scanIdInput.placeholder = "Scan or type " + methodLabel.toLowerCase() + " ID";

    if (scanBtn) {
      scanBtn.textContent = "Scan " + methodLabel;
    }
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

  async function scanCredential() {
    var scannedId = "";
    currentMethod = getSelectedMethod();
    var methodLabel = methodLabels[currentMethod] || "Fingerprint";

    if (config.mode === "api") {
      setStatus(
        "Requesting scanner service for " + methodLabel.toLowerCase() + "...",
        "info",
      );
      try {
        scannedId = await readFromApi();
        setStatus(methodLabel + " captured from scanner API.", "success");
      } catch (error) {
        setStatus("API scan failed, switching to manual entry.", "warning");
      }
    }

    if (config.mode === "webauthn") {
      setStatus("Requesting biometric verification...", "info");
      var verified = await verifyWebAuthn();
      if (verified) {
        setStatus("Biometric verification successful.", "success");
      } else {
        setStatus(
          "Biometric verification unavailable, manual entry required.",
          "warning",
        );
      }
    }

    if (config.mode === "thumb") {
      setStatus(
        "Thumb scan mode enabled. Capture " +
          methodLabel.toLowerCase() +
          " ID to continue.",
        "info",
      );
    }

    if (!scannedId) {
      var promptValue = window.prompt(
        "Enter scanned " + methodLabel + " ID:",
        scanIdInput.value || "",
      );
      if (!promptValue) {
        setStatus("Scan cancelled.", "secondary");
        return;
      }
      scannedId = promptValue.trim();
    }

    scanIdInput.value = scannedId;
    methodInput.value = currentMethod;
  }

  async function submitAttendance(action) {
    var fingerprintId = (scanIdInput.value || "").trim();
    currentMethod = getSelectedMethod();

    if (!fingerprintId) {
      setStatus((methodLabels[currentMethod] || "Scan") + " ID is required.", "warning");
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

  methodInput.addEventListener("change", function () {
    currentMethod = getSelectedMethod();
    updateMethodUi();
  });

  updateMethodUi();
  currentMethod = getSelectedMethod();

  if (scanBtn) {
    scanBtn.addEventListener("click", function () {
      scanCredential();
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
