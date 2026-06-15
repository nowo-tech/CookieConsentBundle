import {
  bindGranularCookieToggles,
  syncCategoryToggleFromCookies,
  syncCookiesFromCategoryToggle,
} from './granular-cookie-toggles';

describe('granular-cookie-toggles', () => {
  const buildCategoryBlock = (): HTMLElement => {
    document.body.innerHTML = `
      <div class="nowo-cookie-consent__category--granular">
        <details open>
          <summary>
            <span data-nowo-toggle>
              <input type="checkbox" class="nowo-cookie-consent__toggle-input" />
              <span class="nowo-cookie-consent__toggle-track"></span>
            </span>
          </summary>
          <table>
            <tr>
              <td>
                <span data-nowo-cookie-toggle>
                  <input type="checkbox" class="nowo-cookie-consent__toggle-input nowo-cookie-consent__cookie-toggle-input" />
                </span>
              </td>
            </tr>
            <tr>
              <td>
                <span data-nowo-cookie-toggle>
                  <input type="checkbox" class="nowo-cookie-consent__toggle-input nowo-cookie-consent__cookie-toggle-input" />
                </span>
              </td>
            </tr>
          </table>
        </details>
      </div>
    `;

    return document.querySelector('.nowo-cookie-consent__category--granular')!;
  };

  it('syncs all cookie toggles when the category master toggle changes', () => {
    const categoryBlock = buildCategoryBlock();
    const categoryInput = categoryBlock.querySelector<HTMLInputElement>('[data-nowo-toggle] input')!;
    const cookieInputs = categoryBlock.querySelectorAll<HTMLInputElement>('.nowo-cookie-consent__cookie-toggle-input');

    categoryInput.checked = true;
    syncCookiesFromCategoryToggle(categoryBlock);

    cookieInputs.forEach((input) => {
      expect(input.checked).toBe(true);
    });
  });

  it('reflects cookie selection on the category master toggle', () => {
    const categoryBlock = buildCategoryBlock();
    const categoryInput = categoryBlock.querySelector<HTMLInputElement>('[data-nowo-toggle] input')!;
    const cookieInputs = categoryBlock.querySelectorAll<HTMLInputElement>('.nowo-cookie-consent__cookie-toggle-input');

    cookieInputs[0]!.checked = true;
    cookieInputs[1]!.checked = false;

    syncCategoryToggleFromCookies(categoryBlock);

    expect(categoryInput.checked).toBe(true);
    expect(categoryInput.indeterminate).toBe(true);
  });

  it('binds change listeners for category and cookie toggles', () => {
    const categoryBlock = buildCategoryBlock();
    const modal = document.createElement('div');
    modal.appendChild(categoryBlock);

    bindGranularCookieToggles(modal);

    const categoryInput = categoryBlock.querySelector<HTMLInputElement>('[data-nowo-toggle] input')!;
    const cookieInputs = categoryBlock.querySelectorAll<HTMLInputElement>('.nowo-cookie-consent__cookie-toggle-input');

    categoryInput.checked = true;
    categoryInput.dispatchEvent(new Event('change', { bubbles: true }));

    cookieInputs.forEach((input) => {
      expect(input.checked).toBe(true);
    });
  });

  it('ignores sync helpers when category toggles are missing', () => {
    document.body.innerHTML = '<div class="nowo-cookie-consent__category--granular"></div>';
    const categoryBlock = document.querySelector('.nowo-cookie-consent__category--granular')!;

    expect(() => syncCategoryToggleFromCookies(categoryBlock)).not.toThrow();
    expect(() => syncCookiesFromCategoryToggle(categoryBlock)).not.toThrow();
  });

  it('clears indeterminate state when syncing cookies from the category toggle', () => {
    const categoryBlock = buildCategoryBlock();
    const categoryInput = categoryBlock.querySelector<HTMLInputElement>('[data-nowo-toggle] input')!;

    categoryInput.indeterminate = true;
    categoryInput.checked = false;
    syncCookiesFromCategoryToggle(categoryBlock);

    expect(categoryInput.indeterminate).toBe(false);
  });
});
