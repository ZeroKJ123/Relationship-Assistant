let messages;

/** Text for a script-driven status in the reader's language; the layout supplies the list. */
export function message(key) {
    if (messages === undefined) {
        const source = globalThis.document?.querySelector('meta[name="app-messages"]');
        messages = source ? JSON.parse(source.content) : {};
    }
    return messages[key] ?? key;
}
