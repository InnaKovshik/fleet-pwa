
async function processQueue() {
    const db = await openDB(); // brauchst du ggf. helper

    const tx = db.transaction("queue", "readwrite");
    const store = tx.objectStore("queue");
    const items = await store.getAll();

    for (const item of items) {
        try {
            await fetch("/wp-json/kfz-pwa/v1/" + item.endpoint, {
                method: item.method,
                body: JSON.stringify(item.body),
                headers: {
                    "Content-Type": "application/json"
                }
            });

            store.delete(item.id);
        } catch (e) {
            break;
        }
    }
}
