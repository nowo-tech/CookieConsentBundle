export interface VisualConfigOptions {
  layout?: string;
  variant?: string;
  positionY?: string;
  positionX?: string;
  equalWeightButtons?: boolean;
  flipButtons?: boolean;
  disablePageInteraction?: boolean;
}

const LAYOUTS = ['bar', 'box', 'cloud'] as const;
const VARIANTS = ['wide', 'inline'] as const;
const POSITIONS_Y = ['top', 'middle', 'bottom'] as const;
const POSITIONS_X = ['left', 'center', 'right'] as const;

const MODIFIER_PREFIX = 'nowo-cookie-consent--';

function removeModifiers(element: HTMLElement, suffixes: readonly string[]): void {
  suffixes.forEach((suffix) => element.classList.remove(`${MODIFIER_PREFIX}${suffix}`));
}

export function applyVisualConfig(modalElement: HTMLElement, options: VisualConfigOptions): void {
  const dialog = modalElement.querySelector<HTMLElement>('.modal-dialog, .nowo-cookie-consent__dialog');

  removeModifiers(modalElement, [
    ...LAYOUTS.map((value) => `layout-${value}`),
    ...VARIANTS.map((value) => `variant-${value}`),
    ...POSITIONS_Y.map((value) => `pos-y-${value}`),
    ...POSITIONS_X.map((value) => `pos-x-${value}`),
    'equal-weight-buttons',
    'flip-buttons',
    'disable-page-interaction',
  ]);

  if (dialog) {
    removeModifiers(dialog, LAYOUTS.map((value) => `dialog-${value}`));
  }

  if (options.layout && LAYOUTS.includes(options.layout as (typeof LAYOUTS)[number])) {
    modalElement.classList.add(`${MODIFIER_PREFIX}layout-${options.layout}`);
    dialog?.classList.add(`${MODIFIER_PREFIX}dialog-${options.layout}`);
  }

  if (options.variant && VARIANTS.includes(options.variant as (typeof VARIANTS)[number])) {
    modalElement.classList.add(`${MODIFIER_PREFIX}variant-${options.variant}`);
  }

  if (options.positionY && POSITIONS_Y.includes(options.positionY as (typeof POSITIONS_Y)[number])) {
    modalElement.classList.add(`${MODIFIER_PREFIX}pos-y-${options.positionY}`);
  }

  if (options.positionX && POSITIONS_X.includes(options.positionX as (typeof POSITIONS_X)[number])) {
    modalElement.classList.add(`${MODIFIER_PREFIX}pos-x-${options.positionX}`);
  }

  if (options.equalWeightButtons) {
    modalElement.classList.add(`${MODIFIER_PREFIX}equal-weight-buttons`);
  }

  if (options.flipButtons) {
    modalElement.classList.add(`${MODIFIER_PREFIX}flip-buttons`);
  }

  if (options.disablePageInteraction) {
    modalElement.classList.add(`${MODIFIER_PREFIX}disable-page-interaction`);
  }

  if (options.layout) {
    modalElement.dataset.nowoLayout = options.layout;
  }

  if (options.variant) {
    modalElement.dataset.nowoVariant = options.variant;
  }

  if (options.positionY) {
    modalElement.dataset.nowoPositionY = options.positionY;
  }

  if (options.positionX) {
    modalElement.dataset.nowoPositionX = options.positionX;
  }

  if (typeof options.equalWeightButtons === 'boolean') {
    modalElement.dataset.nowoEqualWeightButtons = options.equalWeightButtons ? 'true' : 'false';
  }

  if (typeof options.flipButtons === 'boolean') {
    modalElement.dataset.nowoFlipButtons = options.flipButtons ? 'true' : 'false';
  }

  if (typeof options.disablePageInteraction === 'boolean') {
    modalElement.dataset.nowoDisablePageInteraction = options.disablePageInteraction ? 'true' : 'false';
  }
}

export function applyVisualConfigFromElement(modalElement: HTMLElement): void {
  applyVisualConfig(modalElement, {
    layout: modalElement.dataset.nowoLayout,
    variant: modalElement.dataset.nowoVariant,
    positionY: modalElement.dataset.nowoPositionY,
    positionX: modalElement.dataset.nowoPositionX,
    equalWeightButtons: modalElement.dataset.nowoEqualWeightButtons === 'true',
    flipButtons: modalElement.dataset.nowoFlipButtons === 'true',
    disablePageInteraction: modalElement.dataset.nowoDisablePageInteraction === 'true',
  });
}

export function setPageInteractionBlocked(modalElement: HTMLElement, blocked: boolean): void {
  if (modalElement.dataset.nowoDisablePageInteraction !== 'true') {
    return;
  }

  document.body.classList.toggle('nowo-cookie-consent-page-blocked', blocked);
}
