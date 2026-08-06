window.FleetDB = (function() {

    const DB_NAME = "fleet_pwa";
    const DB_VERSION = 4;

    const STORE_DEVICE = "device";
    const STORE_USER = "user";
    const STORE_CARS = "cars";
    const STORE_TRIPS = "trips";
    const STORE_QUEUE = "queue";

    let db = null;

    async function openDB() {
        if (db) return db;
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = e => {
                const database = e.target.result;
                if(!database.objectStoreNames.contains(STORE_DEVICE)) database.createObjectStore(STORE_DEVICE, {keyPath:"id"});
                if(!database.objectStoreNames.contains(STORE_USER)) database.createObjectStore(STORE_USER, {keyPath:"id"});
                if(!database.objectStoreNames.contains(STORE_CARS)) database.createObjectStore(STORE_CARS, {keyPath:"id"});
                if(!database.objectStoreNames.contains(STORE_TRIPS)) database.createObjectStore(STORE_TRIPS, {keyPath:"fahrtid"});
                if(!database.objectStoreNames.contains(STORE_QUEUE)) database.createObjectStore(STORE_QUEUE, {keyPath:"id", autoIncrement:true});
            };

            request.onsuccess = e => {
                db = e.target.result;
                resolve(db);
            };
            request.onerror = reject;
        });
    }

    async function save(store, obj) {
        const database = await openDB();
        return new Promise((resolve, reject) => {
            const tx = database.transaction(store, "readwrite");
            tx.objectStore(store).put(obj);
            tx.oncomplete = () => resolve(true);
            tx.onerror = reject;
        });
    }

    async function get(store, key) {
        const database = await openDB();
        return new Promise((resolve, reject) => {
            const tx = database.transaction(store, "readonly");
            const req = tx.objectStore(store).get(key);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = reject;
        });
    }

    async function addQueue(item) {
        const database = await openDB();
        return new Promise((resolve, reject) => {
            const tx = database.transaction(STORE_QUEUE, "readwrite");
            tx.objectStore(STORE_QUEUE).add({
                endpoint: item.endpoint,
                method: item.method,
                body: JSON.stringify(item.body || {}),
                created: Date.now()
            });
            tx.oncomplete = () => resolve(true);
            tx.onerror = reject;
        });
    }

    async function getQueue() {
        const database = await openDB();
        return new Promise((resolve, reject) => {
            const tx = database.transaction(STORE_QUEUE, "readonly");
            const req = tx.objectStore(STORE_QUEUE).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = reject;
        });
    }

    async function getAllCars() {
    const database = await openDB();
    return new Promise((resolve, reject) => {
        const tx = database.transaction(STORE_CARS, "readonly");
        const req = tx.objectStore(STORE_CARS).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = reject;
    });
}

    async function deleteQueue(id) {
        const database = await openDB();
        return new Promise((resolve, reject) => {
            const tx = database.transaction(STORE_QUEUE, "readwrite");
            tx.objectStore(STORE_QUEUE).delete(id);
            tx.oncomplete = () => resolve(true);
            tx.onerror = reject;
        });
    }

    return {
        openDB,
        saveDevice: obj => save(STORE_DEVICE, {...obj, id:1}),
        getDevice: () => get(STORE_DEVICE, 1),
        saveUser: obj => save(STORE_USER, {...obj, id:1}),
        getUser: () => get(STORE_USER, 1),
        saveCars: async cars => {
            const database = await openDB();
            const tx = database.transaction(STORE_CARS, "readwrite");
            cars.forEach(c => tx.objectStore(STORE_CARS).put(c));
        },
        getCar: id => get(STORE_CARS, Number(id)),
        addQueue,
        getQueue,
        deleteQueue
    };
})();
