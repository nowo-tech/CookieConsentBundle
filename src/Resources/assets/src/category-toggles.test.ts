import { bindCategoryToggles } from './category-toggles';

describe('bindCategoryToggles', () => {
  it('stops click propagation on category toggle wrappers', () => {
    document.body.innerHTML = `
      <div id="cookieconsent">
        <details open>
          <summary>
            <span data-nowo-toggle><input type="checkbox" /></span>
          </summary>
        </details>
      </div>
    `;

    const modal = document.getElementById('cookieconsent')!;
    const summary = modal.querySelector('summary')!;
    const toggle = modal.querySelector('[data-nowo-toggle]')!;
    let summaryClicked = false;

    summary.addEventListener('click', () => {
      summaryClicked = true;
    });

    bindCategoryToggles(modal);

    toggle.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    expect(summaryClicked).toBe(false);
  });
});
