// Regex-based PHP file parsers — no AST needed because the SM Portal follows strict Laravel conventions
function extractClassName(content) {
    const m = content.match(/(?:class|interface|enum)\s+(\w+)/);
    return m?.[1] ?? 'Unknown';
}
function extractArrayValues(content, varName) {
    const regex = new RegExp(`\\$${varName}\\s*=\\s*\\[([^\\]]*?)\\]`, 's');
    const m = content.match(regex);
    if (!m)
        return [];
    return [...m[1].matchAll(/'([^']+)'/g)].map(x => x[1]);
}
function extractCasts(content) {
    const m = content.match(/\$casts\s*=\s*\[([^\]]*?)\]/s);
    if (!m)
        return {};
    const result = {};
    const pairs = m[1].matchAll(/'([^']+)'\s*=>\s*'([^']+)'/g);
    for (const [, key, val] of pairs) {
        result[key] = val;
    }
    return result;
}
function extractTraits(content) {
    const traits = [];
    const useMatches = content.matchAll(/use\s+([\w,\s\\]+?);/g);
    for (const m of useMatches) {
        const parts = m[1].split(',').map(s => s.trim().split('\\').pop().trim());
        traits.push(...parts.filter(p => !p.includes(' ')));
    }
    return [...new Set(traits)];
}
function extractRelationships(content) {
    const rels = [];
    const pattern = /public\s+function\s+(\w+)\s*\(\s*\)\s*(?::\s*[\w\\|]+)?\s*\{[^}]*\breturn\s+\$this->(hasMany|hasOne|belongsTo|belongsToMany|hasManyThrough|hasOneThrough|morphTo|morphMany|morphOne)\s*\(\s*([^,)]+)/g;
    for (const m of content.matchAll(pattern)) {
        const related = m[3].trim().replace(/::class$/, '').split('\\').pop().replace(/['"]/g, '');
        rels.push({ name: m[1], type: m[2], related });
    }
    return rels;
}
function extractScopes(content) {
    return [...content.matchAll(/public\s+function\s+(scope(\w+))\s*\(/g)].map(m => m[2]);
}
export function parseModel(content) {
    const name = extractClassName(content);
    const tableMatch = content.match(/protected\s+\$table\s*=\s*'([^']+)'/);
    return {
        name,
        table: tableMatch?.[1],
        fillable: extractArrayValues(content, 'fillable'),
        hidden: extractArrayValues(content, 'hidden'),
        casts: extractCasts(content),
        traits: extractTraits(content),
        relationships: extractRelationships(content),
        scopes: extractScopes(content),
        softDeletes: content.includes('SoftDeletes'),
    };
}
export function parseService(content) {
    const name = extractClassName(content);
    // Constructor deps — type-hinted params
    const ctorMatch = content.match(/public\s+function\s+__construct\s*\(([^)]*)\)/s);
    const constructorDeps = [];
    if (ctorMatch) {
        const params = ctorMatch[1].matchAll(/(\w+)\s+\$\w+/g);
        for (const m of params)
            constructorDeps.push(m[1]);
    }
    // Public method names (skip __construct)
    const publicMethods = [...content.matchAll(/public\s+function\s+(\w+)\s*\(/g)]
        .map(m => m[1])
        .filter(n => n !== '__construct');
    // Import statements (use X\Y\Z)
    const imports = [...content.matchAll(/^use\s+([\w\\]+);/gm)].map(m => m[1].split('\\').pop());
    return {
        name,
        constructorDeps,
        publicMethods,
        usesTransaction: content.includes('DB::transaction'),
        usesLockForUpdate: content.includes('lockForUpdate'),
        imports,
    };
}
export function parseController(content) {
    const name = extractClassName(content);
    // Injected service — constructor type hints
    const ctorMatch = content.match(/public\s+function\s+__construct\s*\(([^)]*)\)/s);
    let service;
    if (ctorMatch) {
        const m = ctorMatch[1].match(/(\w+Service)\s+\$/);
        if (m)
            service = m[1];
    }
    const publicMethods = [...content.matchAll(/public\s+function\s+(\w+)\s*\(/g)]
        .map(m => m[1])
        .filter(n => n !== '__construct');
    return {
        name,
        service,
        publicMethods,
        usesPolicy: content.includes('$this->authorize') || content.includes('Gate::'),
    };
}
export function parseRequest(content) {
    const name = extractClassName(content);
    const rulesMatch = content.match(/public\s+function\s+rules\s*\(\s*\)\s*(?::\s*array)?\s*\{([^}]+)\}/s);
    const rules = {};
    if (rulesMatch) {
        const pairs = rulesMatch[1].matchAll(/'([^']+)'\s*=>\s*([^\n,]+)/g);
        for (const [, key, val] of pairs) {
            rules[key] = val.trim().replace(/,$/, '');
        }
    }
    return { name, rules };
}
export function parsePolicy(content) {
    const name = extractClassName(content);
    const methods = [];
    const methodPattern = /public\s+function\s+(\w+)\s*\([^)]*\)\s*(?::\s*[\w|]+)?\s*\{([^}]+)\}/gs;
    for (const m of content.matchAll(methodPattern)) {
        if (m[1] === '__construct')
            continue;
        const roleChecks = [];
        const body = m[2];
        const roleMatches = body.matchAll(/Role::(\w+)|hasRole\(['"]([^'"]+)['"]\)|'([^']+)'/g);
        for (const r of roleMatches) {
            const role = r[1] ?? r[2] ?? r[3];
            if (role && !roleChecks.includes(role))
                roleChecks.push(role);
        }
        methods.push({ name: m[1], roleChecks });
    }
    return { name, methods };
}
export function extractDependencies(controllerContent, serviceContent) {
    const name = extractClassName(controllerContent);
    // Service injected in constructor
    const ctorMatch = controllerContent.match(/public\s+function\s+__construct\s*\(([^)]*)\)/s);
    let service;
    if (ctorMatch) {
        const m = ctorMatch[1].match(/(\w+Service)\s+\$/);
        if (m)
            service = m[1];
    }
    // Models imported by controller or service
    const allContent = [controllerContent, serviceContent ?? ''].join('\n');
    const models = [];
    const modelImports = allContent.matchAll(/use\s+App\\Models\\(\w+);/g);
    for (const m of modelImports) {
        if (!models.includes(m[1]))
            models.push(m[1]);
    }
    const imports = [...allContent.matchAll(/^use\s+([\w\\]+);/gm)].map(m => m[1]);
    return { controller: name, service, models, imports };
}
//# sourceMappingURL=parser.js.map