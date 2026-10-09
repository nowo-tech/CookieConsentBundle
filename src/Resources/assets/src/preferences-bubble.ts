/**
 * Preferences bubble helpers.
 *
 * The bubble accent colour is rendered as `data-nowo-bubble-accent` (not an inline `style`
 * attribute) so pages with a strict `style-src-attr` CSP keep it; CSSOM writes are allowed.
 */

export const BUBBLE_ACCENT_PROPERTY = '--nowo-cc-bubble-accent';

/**
 * Copies `data-nowo-bubble-accent` into the `--nowo-cc-bubble-accent` custom property.
 */
export function applyPreferencesBubbleAccents(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>('[data-nowo-bubble-accent]').forEach((element) => {
    const accent = element.dataset.nowoBubbleAccent?.trim() ?? '';
    if (accent !== '') {
      element.style.setProperty(BUBBLE_ACCENT_PROPERTY, accent);
    }
  });
}
