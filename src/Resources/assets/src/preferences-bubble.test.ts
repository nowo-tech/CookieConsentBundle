import { beforeEach, describe, expect, it } from 'vitest';
import { applyPreferencesBubbleAccents, BUBBLE_ACCENT_PROPERTY } from './preferences-bubble';

describe('applyPreferencesBubbleAccents', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  it('sets the accent custom property from the data attribute', () => {
    document.body.innerHTML = '<button data-nowo-open-consent data-nowo-bubble-accent="#ff0000"></button>';

    applyPreferencesBubbleAccents();

    const button = document.querySelector<HTMLElement>('button');
    expect(button?.style.getPropertyValue(BUBBLE_ACCENT_PROPERTY)).toBe('#ff0000');
  });

  it('ignores empty accents', () => {
    document.body.innerHTML = '<button data-nowo-bubble-accent="  "></button>';

    applyPreferencesBubbleAccents(document.body);

    const button = document.querySelector<HTMLElement>('button');
    expect(button?.style.getPropertyValue(BUBBLE_ACCENT_PROPERTY)).toBe('');
  });
});
