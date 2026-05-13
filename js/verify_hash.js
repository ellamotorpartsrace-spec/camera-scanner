const crypto = require("crypto");

const text =
  "DEVELOPED BY BENEDICT RAMIREZ EST 2026 THIS SERVES AS A COPYRIGHT! AND SHALL BE USE ONLY FOR ELLA MOTOR PARTS. ALL RIGHTS RESERVED. TO HAVE A COPY OF THE PROGRAM CONTACT BENEDICT RAMIREZ AT 0997-7855-120";

function getHash(str) {
  const normalized = str.trim().replace(/\s+/g, " ");
  const hash = crypto.createHash("sha256").update(normalized).digest("hex");
  console.log(`Normalized text: "${normalized}"`);
  console.log(`Hash: ${hash}`);
}

getHash(text);
