import { describe, it, expect, beforeEach } from 'vitest';
import { getCached, setCached, invalidate, clearAll, cacheSize } from '../utils/cache.js';

// We test cache behavior using real temp files to verify mtime logic
import { writeFileSync, unlinkSync, mkdtempSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

let tmpDir: string;

beforeEach(() => {
  clearAll();
  tmpDir = mkdtempSync(join(tmpdir(), 'laravel-boost-cache-test-'));
});

describe('cache', () => {
  it('returns null for uncached path', () => {
    expect(getCached('/nonexistent/path.php')).toBeNull();
  });

  it('stores and retrieves a value', () => {
    const filePath = join(tmpDir, 'test.php');
    writeFileSync(filePath, '<?php echo "hello";');

    setCached(filePath, 'parsed-result');
    expect(getCached<string>(filePath)).toBe('parsed-result');
  });

  it('returns null for nonexistent file', () => {
    setCached('/does/not/exist.php', 'value');
    expect(getCached('/does/not/exist.php')).toBeNull();
  });

  it('busts cache when file is modified', async () => {
    const filePath = join(tmpDir, 'modified.php');
    writeFileSync(filePath, '<?php echo "v1";');
    setCached(filePath, 'v1-parsed');

    // Wait briefly then overwrite (triggers mtime change)
    await new Promise(r => setTimeout(r, 10));
    writeFileSync(filePath, '<?php echo "v2";');

    // Cache should be stale
    expect(getCached<string>(filePath)).toBeNull();
  });

  it('tracks cache size', () => {
    const f1 = join(tmpDir, 'a.php');
    const f2 = join(tmpDir, 'b.php');
    writeFileSync(f1, '<?php');
    writeFileSync(f2, '<?php');

    setCached(f1, 'a');
    setCached(f2, 'b');
    expect(cacheSize()).toBe(2);
  });

  it('invalidates a specific entry', () => {
    const filePath = join(tmpDir, 'inv.php');
    writeFileSync(filePath, '<?php');
    setCached(filePath, 'value');
    expect(cacheSize()).toBe(1);

    invalidate(filePath);
    expect(cacheSize()).toBe(0);
  });

  it('clearAll removes all entries', () => {
    const f = join(tmpDir, 'x.php');
    writeFileSync(f, '<?php');
    setCached(f, 'x');
    clearAll();
    expect(cacheSize()).toBe(0);
  });
});
