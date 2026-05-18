import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { BACKEND_PATH } from '../config.js';
import { isContainerRunning, execInDocker, dockerNotRunningError } from '../utils/exec.js';
import { buildResponse, buildError, responseToText } from '../utils/response.js';

function parseMigrationsForTables(): string[] {
  const migrationsDir = resolve(BACKEND_PATH, 'database/migrations');
  if (!existsSync(migrationsDir)) return [];

  const tables: string[] = [];
  try {
    const files = readdirSync(migrationsDir).filter(f => f.endsWith('.php'));
    for (const file of files) {
      const content = readFileSync(join(migrationsDir, file), 'utf-8');
      const m = content.match(/Schema::create\(['"]([^'"]+)['"]/g);
      if (m) {
        for (const match of m) {
          const t = match.match(/Schema::create\(['"]([^'"]+)['"]/);
          if (t?.[1]) tables.push(t[1]);
        }
      }
    }
  } catch {
    // migration dir read failed
  }
  return [...new Set(tables)].sort();
}

function parseMigrationForTable(tableName: string): unknown {
  const migrationsDir = resolve(BACKEND_PATH, 'database/migrations');
  if (!existsSync(migrationsDir)) return null;

  const columns: Array<{ name: string; type: string }> = [];

  try {
    const files = readdirSync(migrationsDir)
      .filter(f => f.endsWith('.php'))
      .sort();

    for (const file of files) {
      const content = readFileSync(join(migrationsDir, file), 'utf-8');
      if (!content.includes(`'${tableName}'`) && !content.includes(`"${tableName}"`)) continue;

      // Parse column definitions
      const colPattern = /\$table->(\w+)\(['"](\w+)['"]/g;
      for (const m of content.matchAll(colPattern)) {
        const type = m[1];
        const name = m[2];
        if (!['primary', 'index', 'unique', 'foreign', 'timestamps', 'softDeletes'].includes(type)) {
          if (!columns.find(c => c.name === name)) {
            columns.push({ name, type });
          }
        }
      }

      // timestamps(), softDeletes(), etc.
      if (content.includes('$table->timestamps()')) {
        if (!columns.find(c => c.name === 'created_at')) columns.push({ name: 'created_at', type: 'timestamp' });
        if (!columns.find(c => c.name === 'updated_at')) columns.push({ name: 'updated_at', type: 'timestamp' });
      }
      if (content.includes('$table->softDeletes()')) {
        if (!columns.find(c => c.name === 'deleted_at')) columns.push({ name: 'deleted_at', type: 'timestamp_nullable' });
      }
    }
  } catch {
    return null;
  }

  return { table: tableName, columns, note: 'parsed from migration files (best-effort)' };
}

export async function handleDbSchema(table?: string): Promise<string> {
  const startMs = Date.now();
  const warnings: string[] = [];

  if (!isContainerRunning()) {
    warnings.push(dockerNotRunningError());
    // Fall back to file parsing
    if (table) {
      const data = parseMigrationForTable(table);
      if (!data) {
        return responseToText(buildError(`Table '${table}' not found in migrations`, { startMs }));
      }
      return responseToText(buildResponse(data, { source: 'files', startMs, warnings }));
    } else {
      const tables = parseMigrationsForTables();
      return responseToText(buildResponse({ tables, total: tables.length }, { source: 'files', startMs, warnings }));
    }
  }

  if (table) {
    const result = execInDocker(`php artisan db:table ${table} --json`);
    if (result.exitCode === 0) {
      try {
        const data = JSON.parse(result.stdout);
        return responseToText(buildResponse(data, { source: 'docker', startMs, containerStatus: 'running' }));
      } catch {
        warnings.push('db:table returned non-JSON, falling back to migration parsing');
        const data = parseMigrationForTable(table);
        return responseToText(buildResponse(data, { source: 'files', startMs, warnings }));
      }
    } else {
      warnings.push(`db:table failed: ${result.stderr.slice(0, 200)}`);
      const data = parseMigrationForTable(table);
      return responseToText(buildResponse(data, { source: 'files', startMs, warnings }));
    }
  } else {
    const result = execInDocker('php artisan db:show --json');
    if (result.exitCode === 0) {
      try {
        const data = JSON.parse(result.stdout);
        return responseToText(buildResponse(data, { source: 'docker', startMs, containerStatus: 'running' }));
      } catch {
        warnings.push('db:show returned non-JSON, falling back to migration parsing');
        const tables = parseMigrationsForTables();
        return responseToText(buildResponse({ tables, total: tables.length }, { source: 'files', startMs, warnings }));
      }
    } else {
      warnings.push(`db:show failed: ${result.stderr.slice(0, 200)}`);
      const tables = parseMigrationsForTables();
      return responseToText(buildResponse({ tables, total: tables.length }, { source: 'files', startMs, warnings }));
    }
  }
}
