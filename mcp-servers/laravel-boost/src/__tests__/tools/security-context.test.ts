import { describe, it, expect, beforeEach } from 'vitest';
import { clearAll } from '../../utils/cache.js';

// We test the parser function directly, not the handler (which reads from disk)
import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
// Root of the workspace: src/__tests__/tools → src/__tests__ → src → laravel-boost → mcp-servers → root
const ROOT = resolve(__dirname, '../../../../..');
const CLAUDE_MD = resolve(ROOT, 'CLAUDE.md');

beforeEach(() => clearAll());

describe('security context', () => {
  it('CLAUDE.md exists', () => {
    expect(existsSync(CLAUDE_MD)).toBe(true);
  });

  it('contains Security Decisions section', () => {
    if (!existsSync(CLAUDE_MD)) return;
    const content = readFileSync(CLAUDE_MD, 'utf-8');
    expect(content).toContain('Security Decisions');
  });

  it('contains at least 15 numbered decisions', () => {
    if (!existsSync(CLAUDE_MD)) return;
    const content = readFileSync(CLAUDE_MD, 'utf-8');
    const matches = [...content.matchAll(/^\d{1,2}\.\s+\*\*/gm)];
    expect(matches.length).toBeGreaterThanOrEqual(15);
  });

  it('decisions cover known topics: rate limiting, token, mass-assignment', () => {
    if (!existsSync(CLAUDE_MD)) return;
    const content = readFileSync(CLAUDE_MD, 'utf-8');
    const lower = content.toLowerCase();
    expect(lower).toContain('rate limit');
    expect(lower).toContain('token');
    expect(lower).toContain('mass-assignment');
  });
});
