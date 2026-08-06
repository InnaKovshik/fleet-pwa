// ================= DB =================
const FleetDB = (function(){
    const DB_NAME = "fleet_pwa";
    const STORE = "queue";
    let db;

    async function open(){
        if(db) return db;
        return new Promise((res,rej)=>{
            const req = indexedDB.open(DB_NAME,1);
            req.onupgradeneeded = e=>{
                e.target.result.createObjectStore(STORE,{keyPath:"id",autoIncrement:true});
            };
            req.onsuccess = e=>{db=e.target.result;res(db)};
            req.onerror = rej;
        });
    }

    async function add(item){
        const d = await open();
        const tx = d.transaction(STORE,"readwrite");
        tx.objectStore(STORE).add(item);
    }

    async function all(){
        const d = await open();
        return new Promise(r=>{
            const req = d.transaction(STORE).objectStore(STORE).getAll();
            req.onsuccess = ()=>r(req.result);
        });
    }

    async function del(id){
        const d = await open();
        d.transaction(STORE,"readwrite").objectStore(STORE).delete(id);
    }

    return {add,all,del};
})();

// ================= API =================
async function api(endpoint,method="GET",body=null){
    try{
        const res = await fetch(FleetConfig.restUrl+endpoint,{
            method,
            headers:{"Content-Type":"application/json"},
            body: body?JSON.stringify(body):null
        });

        if(!res.ok) throw new Error("API error");
        return await res.json();

    }catch(e){
        console.warn("OFFLINE -> queue");
        await FleetDB.add({endpoint,method,body});
        return {offline:true};
    }
}

// ================= SYNC =================
async function processQueue(){
    const items = await FleetDB.all();

    for(const item of items){
        try{
            await fetch(FleetConfig.restUrl+item.endpoint,{
                method:item.method,
                headers:{"Content-Type":"application/json"},
                body:JSON.stringify(item.body)
            });
            await FleetDB.del(item.id);
        }catch(e){
            break;
        }
    }
}

window.addEventListener("online",processQueue);

// ================= UI =================
const video = document.getElementById("video");
const canvas = document.getElementById("canvas");

if(!canvas){
    console.error("Canvas missing");
}

const ctx = canvas?.getContext("2d");

let stream;

async function startScanner(){
    stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:"environment"}});
    video.srcObject = stream;
    video.play();
    requestAnimationFrame(scan);
}

function scan(){
    if(video.readyState === video.HAVE_ENOUGH_DATA){
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video,0,0);

        const img = ctx.getImageData(0,0,canvas.width,canvas.height);
        const code = jsQR(img.data,img.width,img.height);

        if(code){
            stopScanner();
            handleQR(code.data);
            return;
        }
    }
    requestAnimationFrame(scan);
}

function stopScanner(){
    stream?.getTracks().forEach(t=>t.stop());
}

async function handleQR(data){
    const cars = await api("cars");
    const car = cars.find(c=>c.id == data);

    if(!car){
        alert("Car not found");
        return;
    }

    document.getElementById("carId").textContent = car.id;
}

// ================= TRIPS =================
async function startTrip(car_id,km){
    await api("start","POST",{car_id,km_start:km});
}

async function endTrip(fahrt_id,km){
    await api("end","POST",{fahrt_id,km_end:km});
}

// ================= SW =================
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register(FleetConfig.swUrl, {
        scope: '/kfz-pwa/'
    }).then(reg => {
        console.log('SW registered', reg);
    }).catch(err => {
        console.error('SW registration failed', err);
    });
}

// ================= EVENTS =================
document.getElementById("startCameraBtn")?.addEventListener("click",startScanner);
