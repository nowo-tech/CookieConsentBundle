/**
 * Serializes a form for application/x-www-form-urlencoded POST requests.
 *
 * @param form HTML form to serialize.
 * @param clickedButton Submit button that triggered the request.
 * @returns URL-encoded form payload including the clicked button name.
 */
export function serializeForm(form: HTMLFormElement, clickedButton: HTMLButtonElement): string {
  const serialized: string[] = [];

  for (let index = 0; index < form.elements.length; index += 1) {
    const field = form.elements.item(index);

    if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
      continue;
    }

    if ((field.type !== 'checkbox' && field.type !== 'radio' && field.type !== 'button') || field.checked) {
      serialized.push(`${encodeURIComponent(field.name)}=${encodeURIComponent(field.value)}`);
    }
  }

  serialized.push(`${encodeURIComponent(clickedButton.getAttribute('name') ?? '')}=`);

  return serialized.join('&');
}
