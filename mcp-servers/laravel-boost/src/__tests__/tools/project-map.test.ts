import { describe, it, expect, beforeEach } from 'vitest';
import { clearAll } from '../../utils/cache.js';
import { handleProjectMap } from '../../tools/project-map.js';

beforeEach(() => clearAll());

describe('project_map', () => {
  it('returns valid JSON response', async () => {
    const text = await handleProjectMap('models');
    expect(() => JSON.parse(text)).not.toThrow();
  });

  it('response has required envelope fields', async () => {
    const text = await handleProjectMap('models');
    const res = JSON.parse(text);
    expect(res).toHaveProperty('success');
    expect(res).toHaveProperty('source');
    expect(res).toHaveProperty('cached');
    expect(res).toHaveProperty('timestamp');
    expect(res).toHaveProperty('data');
  });

  it('models section returns an array', async () => {
    const text = await handleProjectMap('models');
    const res = JSON.parse(text);
    expect(Array.isArray(res.data.models)).toBe(true);
  });

  it('models include User', async () => {
    const text = await handleProjectMap('models');
    const res = JSON.parse(text);
    const names = res.data.models.map((m: { name: string }) => m.name);
    expect(names).toContain('User');
  });

  it('services section includes CompanyService', async () => {
    const text = await handleProjectMap('services');
    const res = JSON.parse(text);
    const names = res.data.services.map((s: { name: string }) => s.name);
    expect(names).toContain('CompanyService');
  });

  it('controllers section includes FranchiseController', async () => {
    const text = await handleProjectMap('controllers');
    const res = JSON.parse(text);
    const names = res.data.controllers.map((c: { name: string }) => c.name);
    expect(names).toContain('FranchiseController');
  });

  it('all section returns all keys', async () => {
    const text = await handleProjectMap('all');
    const res = JSON.parse(text);
    const keys = Object.keys(res.data);
    expect(keys).toContain('models');
    expect(keys).toContain('services');
    expect(keys).toContain('controllers');
    expect(keys).toContain('requests');
    expect(keys).toContain('policies');
    expect(keys).toContain('dependencies');
  });

  it('dependencies section maps controllers to models', async () => {
    const text = await handleProjectMap('dependencies');
    const res = JSON.parse(text);
    expect(Array.isArray(res.data.dependencies)).toBe(true);
    const deps = res.data.dependencies as Array<{ controller: string; models: string[] }>;
    const franchise = deps.find(d => d.controller === 'FranchiseController');
    expect(franchise).toBeDefined();
  });

  it('source is "files" (no Docker needed)', async () => {
    const text = await handleProjectMap('models');
    const res = JSON.parse(text);
    expect(res.source).toBe('files');
  });
});
