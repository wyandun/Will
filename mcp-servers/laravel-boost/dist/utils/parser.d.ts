export interface ParsedModel {
    name: string;
    table?: string;
    fillable: string[];
    hidden: string[];
    casts: Record<string, string>;
    traits: string[];
    relationships: ParsedRelationship[];
    scopes: string[];
    softDeletes: boolean;
}
export interface ParsedRelationship {
    name: string;
    type: string;
    related: string;
}
export interface ParsedService {
    name: string;
    constructorDeps: string[];
    publicMethods: string[];
    usesTransaction: boolean;
    usesLockForUpdate: boolean;
    imports: string[];
}
export interface ParsedController {
    name: string;
    service?: string;
    publicMethods: string[];
    usesPolicy: boolean;
}
export interface ParsedRequest {
    name: string;
    rules: Record<string, string>;
}
export interface ParsedPolicy {
    name: string;
    methods: ParsedPolicyMethod[];
}
export interface ParsedPolicyMethod {
    name: string;
    roleChecks: string[];
}
export interface ParsedDependencies {
    controller: string;
    service?: string;
    models: string[];
    imports: string[];
}
export declare function parseModel(content: string): ParsedModel;
export declare function parseService(content: string): ParsedService;
export declare function parseController(content: string): ParsedController;
export declare function parseRequest(content: string): ParsedRequest;
export declare function parsePolicy(content: string): ParsedPolicy;
export declare function extractDependencies(controllerContent: string, serviceContent?: string): ParsedDependencies;
//# sourceMappingURL=parser.d.ts.map