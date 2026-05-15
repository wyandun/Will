import { readFileSync, existsSync } from 'node:fs';
import { SECURITY_DECISIONS_FILE, SECURITY_DECISIONS_SECTION } from '../config.js';
import { getCached, setCached } from '../utils/cache.js';
import { buildResponse, buildError, responseToText } from '../utils/response.js';

interface SecurityDecision {
  number: number;
  title: string;
  body: string;
}

function parseSecurityDecisions(content: string, section: string): SecurityDecision[] {
  // Find the section
  const sectionRegex = new RegExp(`## ${section}([\\s\\S]*?)(?=\\n## |$)`);
  const sectionMatch = content.match(sectionRegex);
  if (!sectionMatch) return [];

  const sectionContent = sectionMatch[1];

  // Each decision starts with a number and bold title: `1. **Title**: body`
  const decisions: SecurityDecision[] = [];
  const decisionRegex = /(\d+)\.\s+\*\*([^*]+)\*\*:?\s*([\s\S]*?)(?=\n\d+\.\s+\*\*|\n---|\n##|$)/g;

  for (const m of sectionContent.matchAll(decisionRegex)) {
    decisions.push({
      number: parseInt(m[1], 10),
      title: m[2].trim(),
      body: m[3].trim(),
    });
  }
  return decisions;
}

export async function handleGetSecurityContext(topic?: string): Promise<string> {
  const startMs = Date.now();

  if (!existsSync(SECURITY_DECISIONS_FILE)) {
    return responseToText(buildError(`Security decisions file not found: ${SECURITY_DECISIONS_FILE}`, { startMs }));
  }

  let content = getCached<string>(SECURITY_DECISIONS_FILE);
  if (!content) {
    try {
      content = readFileSync(SECURITY_DECISIONS_FILE, 'utf-8');
      setCached(SECURITY_DECISIONS_FILE, content);
    } catch (err) {
      return responseToText(buildError(`Failed to read CLAUDE.md: ${String(err)}`, { startMs }));
    }
  }

  let decisions = parseSecurityDecisions(content, SECURITY_DECISIONS_SECTION);

  if (decisions.length === 0) {
    return responseToText(buildError(`Section "${SECURITY_DECISIONS_SECTION}" not found in CLAUDE.md`, { startMs }));
  }

  // Filter by topic if provided
  if (topic) {
    const lower = topic.toLowerCase();
    decisions = decisions.filter(
      d =>
        d.title.toLowerCase().includes(lower) ||
        d.body.toLowerCase().includes(lower)
    );
  }

  const data = {
    total: decisions.length,
    filter: topic ?? null,
    note: 'These decisions are FINALIZED. Do NOT re-flag them. They were reviewed across multiple security audit rounds.',
    decisions,
  };

  return responseToText(buildResponse(data, { source: 'files', cached: false, startMs }));
}
