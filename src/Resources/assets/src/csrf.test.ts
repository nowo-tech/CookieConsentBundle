import { afterEach, describe, expect, it, vi } from 'vitest';

import { findCsrfField, prepareCsrfForRequest } from './csrf';

describe('csrf helpers', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    document.cookie.split(';').forEach((part) => {
      const name = part.split('=')[0]?.trim();
      if (name) {
        document.cookie = `${name}=; path=/; max-age=0`;
      }
    });
    vi.unstubAllGlobals();
  });

  it('finds Symfony CSRF fields by data-controller or name', () => {
    document.body.innerHTML = `
      <form>
        <input type="hidden" name="_token" value="csrf-token" data-controller="csrf-protection" />
      </form>
    `;

    const form = document.querySelector('form') as HTMLFormElement;
    expect(findCsrfField(form)?.name).toBe('_token');
  });

  it('generates a double-submit token for SameOrigin placeholders', () => {
    document.body.innerHTML = `
      <form>
        <input type="hidden" name="_token" value="csrf-token" data-controller="csrf-protection" />
      </form>
    `;

    const form = document.querySelector('form') as HTMLFormElement;
    const token = prepareCsrfForRequest(form);
    const field = findCsrfField(form);

    expect(token).toBeTruthy();
    expect(token?.length).toBeGreaterThanOrEqual(24);
    expect(field?.value).toBe(token);
    expect(field?.getAttribute('data-csrf-protection-cookie-value')).toBe('csrf-token');
    expect(document.cookie).toContain(`csrf-token_${token}=csrf-token`);
  });

  it('leaves session CSRF tokens unchanged', () => {
    const sessionToken = 'a'.repeat(32);
    document.body.innerHTML = `
      <form>
        <input type="hidden" name="_token" value="${sessionToken}" data-controller="csrf-protection" />
      </form>
    `;

    const form = document.querySelector('form') as HTMLFormElement;
    const token = prepareCsrfForRequest(form);

    expect(token).toBe(sessionToken);
    expect(findCsrfField(form)?.value).toBe(sessionToken);
    expect(document.cookie).not.toContain(`csrf-token_${sessionToken}`);
  });

  it('returns null when the form has no CSRF field', () => {
    document.body.innerHTML = `<form><input name="analytics" value="1" /></form>`;
    const form = document.querySelector('form') as HTMLFormElement;

    expect(prepareCsrfForRequest(form)).toBeNull();
  });
});
