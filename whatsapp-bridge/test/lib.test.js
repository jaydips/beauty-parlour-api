import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeIndianMobile, keyMatches, RateLimiter } from '../lib.js';

test('normalizeIndianMobile accepts the usual spellings of an Indian mobile', () => {
  assert.equal(normalizeIndianMobile('9876543210'), '919876543210');
  assert.equal(normalizeIndianMobile('+91 98765 43210'), '919876543210');
  assert.equal(normalizeIndianMobile('09876543210'), '919876543210');
  assert.equal(normalizeIndianMobile('919876543210'), '919876543210');
  assert.equal(normalizeIndianMobile('1234567890'), null);
  assert.equal(normalizeIndianMobile(''), null);
  assert.equal(normalizeIndianMobile(undefined), null);
});

test('keyMatches compares in constant time and rejects empty keys', () => {
  assert.equal(keyMatches('abc', 'abc'), true);
  assert.equal(keyMatches('abc', 'abd'), false);
  assert.equal(keyMatches('', 'abc'), false);
  assert.equal(keyMatches('abc', ''), false);
  assert.equal(keyMatches(undefined, 'abc'), false);
});

test('RateLimiter allows max hits per window', () => {
  let t = 0;
  const limiter = new RateLimiter(2, 1000, () => t);
  assert.equal(limiter.take(), true);
  assert.equal(limiter.take(), true);
  assert.equal(limiter.take(), false);
  t = 1001;
  assert.equal(limiter.take(), true);
});
