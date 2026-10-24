import crypto from 'node:crypto';

/**
 * 9876543210 / +91 98765 43210 / 919876543210 -> "919876543210"; anything else -> null.
 * Indian mobiles only (the parlour's customers): 10 digits starting 6-9.
 */
export function normalizeIndianMobile(value) {
  const digits = String(value ?? '').replace(/\D+/g, '');
  const local =
    digits.length === 12 && digits.startsWith('91')
      ? digits.slice(2)
      : digits.length === 11 && digits.startsWith('0')
        ? digits.slice(1)
        : digits;
  return /^[6-9]\d{9}$/.test(local) ? `91${local}` : null;
}

/** Constant-time comparison of the X-Bridge-Key header. */
export function keyMatches(given, expected) {
  if (typeof given !== 'string' || given === '' || expected === '') return false;
  const a = crypto.createHash('sha256').update(given).digest();
  const b = crypto.createHash('sha256').update(expected).digest();
  return crypto.timingSafeEqual(a, b);
}

/** Sliding-window limit on outgoing messages (protects the WhatsApp number from being flagged). */
export class RateLimiter {
  constructor(max, windowMs, now = () => Date.now()) {
    this.max = max;
    this.windowMs = windowMs;
    this.now = now;
    this.hits = [];
  }

  take() {
    const t = this.now();
    this.hits = this.hits.filter((h) => t - h < this.windowMs);
    if (this.hits.length >= this.max) return false;
    this.hits.push(t);
    return true;
  }
}
