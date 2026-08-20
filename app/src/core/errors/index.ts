/**
 * Typed domain errors. Every error carries a stable `code` for i18n + logging.
 * Never throw bare Error from domain/application layers.
 */
export class DomainError extends Error {
  readonly code: string;
  readonly httpStatus: number;
  readonly details?: Record<string, unknown>;
  constructor(code: string, message: string, httpStatus = 400, details?: Record<string, unknown>) {
    super(message);
    this.name = new.target.name;
    this.code = code;
    this.httpStatus = httpStatus;
    this.details = details;
  }
}

export class ValidationError extends DomainError {
  constructor(message: string, details?: Record<string, unknown>) {
    super('validation_error', message, 422, details);
  }
}

export class PermissionDeniedError extends DomainError {
  constructor(permission: string, details?: Record<string, unknown>) {
    super('permission_denied', `Permission denied: ${permission}`, 403, { permission, ...details });
  }
}

/** Accounting integrity guards — these must never be swallowed. */
export class UnbalancedEntryError extends DomainError {
  constructor(debitMinor: bigint, creditMinor: bigint) {
    super('unbalanced_entry', `Journal entry not balanced: debit=${debitMinor} credit=${creditMinor}`, 422, {
      debitMinor: debitMinor.toString(),
      creditMinor: creditMinor.toString(),
    });
  }
}

export class PeriodClosedError extends DomainError {
  constructor(periodId: string) {
    super('period_closed', `Cannot post into a closed period: ${periodId}`, 409, { periodId });
  }
}

export class PostedImmutableError extends DomainError {
  constructor(entityType: string, entityId: string) {
    super('posted_immutable', `Posted ${entityType} is immutable and cannot be edited/deleted: ${entityId}`, 409, {
      entityType,
      entityId,
    });
  }
}

export class CurrencyMismatchError extends DomainError {
  constructor(a: string, b: string) {
    super('currency_mismatch', `Currency mismatch: ${a} vs ${b}`, 422, { a, b });
  }
}

export class NegativeStockError extends DomainError {
  constructor(productId: string, warehouseId: string) {
    super('negative_stock', `Insufficient stock for product ${productId} in warehouse ${warehouseId}`, 409, {
      productId,
      warehouseId,
    });
  }
}

export class ControlAccountViolationError extends DomainError {
  constructor(accountCode: string) {
    super('control_account_violation', `Control account ${accountCode} may only be posted by its subledger`, 409, {
      accountCode,
    });
  }
}

export class UnauthenticatedError extends DomainError {
  constructor() {
    super('unauthenticated', 'Authentication required', 401);
  }
}

export class InvalidCredentialsError extends DomainError {
  // Generic on purpose — never reveals whether the email exists.
  constructor() {
    super('invalid_credentials', 'Invalid email or password', 401);
  }
}

export class RateLimitedError extends DomainError {
  constructor(retryAfterMs: number) {
    super('rate_limited', 'Too many attempts, please try again later', 429, { retryAfterMs });
  }
}

export class CrossTenantError extends DomainError {
  constructor() {
    // Do not leak the other company's id.
    super('cross_tenant', 'Resource does not belong to the current company', 403);
  }
}
