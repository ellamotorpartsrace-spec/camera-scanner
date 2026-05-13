async function getCopyrightHash() {
  const banner = document.querySelector(".copyright-content");
  if (!banner) return null;

  const text = banner.innerText.trim().replace(/\s+/g, " ");
  const encoder = new TextEncoder();
  const data = encoder.encode(text);
  const hashBuffer = await crypto.subtle.digest("SHA-256", data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map((b) => b.toString(16).padStart(2, "0")).join("");
}
