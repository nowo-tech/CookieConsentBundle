import { describe, expect, it } from 'vitest';

import { serializeForm } from './form-serializer';

function createForm(html: string): { form: HTMLFormElement; button: HTMLButtonElement } {
  document.body.innerHTML = html;
  const form = document.querySelector('form') as HTMLFormElement;
  const button = document.querySelector('button[type="submit"]') as HTMLButtonElement;

  return { form, button };
}

describe('serializeForm', () => {
  it('serializes text inputs and the clicked submit button', () => {
    const { form, button } = createForm(`
      <form>
        <input type="text" name="foo" value="bar" />
        <input type="checkbox" name="enabled" value="1" checked />
        <input type="checkbox" name="disabled" value="1" />
        <button type="submit" name="save">Save</button>
      </form>
    `);

    const payload = serializeForm(form, button);

    expect(payload).toContain('foo=bar');
    expect(payload).toContain('enabled=1');
    expect(payload).not.toContain('disabled=1');
    expect(payload.endsWith('save=')).toBe(true);
  });

  it('includes checked radio values only', () => {
    const { form, button } = createForm(`
      <form>
        <input type="radio" name="choice" value="a" />
        <input type="radio" name="choice" value="b" checked />
        <button type="submit" name="accept">Accept</button>
      </form>
    `);

    const payload = serializeForm(form, button);

    expect(payload).toContain('choice=b');
    expect(payload).not.toContain('choice=a');
  });

  it('serializes select and textarea fields', () => {
    const { form, button } = createForm(`
      <form>
        <select name="locale">
          <option value="es" selected>Spanish</option>
          <option value="en">English</option>
        </select>
        <textarea name="notes">hello</textarea>
        <button type="submit" name="save">Save</button>
      </form>
    `);

    const payload = serializeForm(form, button);

    expect(payload).toContain('locale=es');
    expect(payload).toContain('notes=hello');
  });
});
