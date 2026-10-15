// La interfaz llama a los mismos endpoints que usamos en Postman.
const byId = (id) => document.getElementById(id);
let token = null;
let trips = [];
let editingId = null;
let busy = false;
const addressSearches = [];
const statuses = { pending: 'Pendiente', completed: 'Completado', cancelled: 'Cancelado' };

function notify(message, error = false) {
    const notice = byId('notice');
    notice.textContent = message;
    notice.classList.toggle('error', error);
    notice.hidden = !message;
}

function showAuthTab(register) {
    byId('register-form').hidden = !register;
    byId('login-form').hidden = register;
    byId('register-tab').classList.toggle('active', register);
    byId('login-tab').classList.toggle('active', !register);
    byId('register-tab').setAttribute('aria-pressed', String(register));
    byId('login-tab').setAttribute('aria-pressed', String(!register));
    notify('');
}

function showSignedOut() {
    token = null;
    trips = [];
    byId('trips-body').replaceChildren();
    byId('auth-view').hidden = false;
    byId('dashboard').hidden = true;
    byId('account').hidden = true;
    byId('user-name').textContent = '';
    resetEditor();
    showAuthTab(false);
}

async function api(path, method = 'GET', data) {
    const headers = { Accept: 'application/json' };
    if (data !== undefined) headers['Content-Type'] = 'application/json';
    if (token) headers.Authorization = `Bearer ${token}`;
    let response;
    try {
        response = await fetch(`/api${path}`, { method, headers, body: data === undefined ? undefined : JSON.stringify(data) });
    } catch {
        throw new Error('No se pudo conectar. Comprueba que el servidor esté encendido.');
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 401 && token) showSignedOut();
        const errors = Object.values(result.errors || {}).flat();
        throw new Error(errors.length ? errors.join('\n') : (response.status === 401 ? 'Inicia sesión para continuar.' : result.message || 'No se pudo completar la operación.'));
    }
    return result;
}

async function run(action) {
    if (busy) return;
    busy = true;
    document.querySelectorAll('button').forEach((button) => { button.disabled = true; });
    notify('Procesando…');
    try { await action(); } catch (error) { notify(error.message, true); }
    finally {
        busy = false;
        document.querySelectorAll('button').forEach((button) => { button.disabled = false; });
    }
}

function resetEditor() {
    addressSearches.forEach((search) => search.reset());
    editingId = null;
    byId('trip-form').reset();
    byId('editor-title').textContent = 'Nuevo viaje';
    byId('editor-description').textContent = 'Elige el destino. Nosotros guardamos los detalles.';
    byId('save-trip').textContent = 'Guardar viaje';
    byId('cancel-edit').hidden = true;
    byId('status-field').hidden = true;
}

function cell(row, text, className) {
    const td = document.createElement('td');
    td.textContent = text;
    if (className) td.className = className;
    row.append(td);
    return td;
}

function renderTrips() {
    const body = byId('trips-body');
    body.replaceChildren();
    byId('trip-count').textContent = trips.length;
    byId('empty').hidden = trips.length !== 0;
    byId('table-wrap').hidden = trips.length === 0;
    for (const trip of trips) {
        const row = document.createElement('tr');
        const route = cell(row, '');
        const label = document.createElement('span');
        label.className = 'route-label';
        label.textContent = `${trip.origin} → ${trip.destination}`;
        const id = document.createElement('span');
        id.className = 'trip-id';
        id.textContent = `Viaje #${trip.id}`;
        route.append(label, id);
        cell(row, new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short', timeZone: 'UTC' }).format(new Date(trip.departure_at)));
        cell(row, Number(trip.price).toFixed(2));
        const badge = document.createElement('span');
        badge.className = `badge ${Object.hasOwn(statuses, trip.status) ? trip.status : ''}`;
        badge.textContent = statuses[trip.status] || trip.status;
        cell(row, '').append(badge);
        const actions = document.createElement('div');
        actions.className = 'actions';
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'button secondary';
        edit.textContent = 'Editar';
        edit.setAttribute('aria-label', `Editar viaje ${trip.id}`);
        edit.addEventListener('click', () => run(async () => {
            const current = await api(`/trips/${trip.id}`);
            addressSearches.forEach((search) => search.reset());
            editingId = current.id;
            for (const key of ['origin', 'destination', 'price', 'status']) byId(key).value = current[key];
            byId('departure_at').value = current.departure_at.slice(0, 16);
            byId('editor-title').textContent = `Editar viaje #${current.id}`;
            byId('editor-description').textContent = 'Modifica los datos y guarda los cambios.';
            byId('save-trip').textContent = 'Guardar cambios';
            byId('cancel-edit').hidden = false;
            byId('status-field').hidden = false;
            notify('');
            byId('origin').focus();
            byId('trip-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }));
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button danger';
        remove.textContent = 'Eliminar';
        remove.setAttribute('aria-label', `Eliminar viaje ${trip.id}`);
        remove.addEventListener('click', () => {
            if (busy || !window.confirm(`¿Eliminar el viaje de ${trip.origin} a ${trip.destination}?`)) return;
            run(async () => {
                await api(`/trips/${trip.id}`, 'DELETE');
                if (editingId === trip.id) resetEditor();
                await loadTrips();
                notify('Viaje eliminado correctamente.');
            });
        });
        edit.disabled = busy;
        remove.disabled = busy;
        actions.append(edit, remove);
        cell(row, '').append(actions);
        body.append(row);
    }
}

async function loadTrips() {
    trips = await api('/trips');
    renderTrips();
}

byId('login-tab').addEventListener('click', () => showAuthTab(false));
byId('register-tab').addEventListener('click', () => showAuthTab(true));
byId('register-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const data = Object.fromEntries(new FormData(event.target));
        await api('/register', 'POST', data);
        event.target.reset();
        showAuthTab(false);
        byId('login-email').value = data.email;
        byId('login-password').focus();
        notify('Cuenta creada. Ya puedes iniciar sesión.');
    });
});
byId('login-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const result = await api('/login', 'POST', Object.fromEntries(new FormData(event.target)));
        token = result.token;
        event.target.reset();
        byId('user-name').textContent = result.user.name;
        byId('account').hidden = false;
        byId('auth-view').hidden = true;
        byId('dashboard').hidden = false;
        await loadTrips();
        notify('');
    });
});
byId('logout').addEventListener('click', () => run(async () => {
    await api('/logout', 'POST');
    showSignedOut();
    notify('Sesión cerrada correctamente.');
}));
byId('refresh').addEventListener('click', () => run(async () => { await loadTrips(); notify('Lista actualizada.'); }));
byId('cancel-edit').addEventListener('click', () => { resetEditor(); notify(''); });
byId('trip-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        addressSearches.forEach((search) => search.reset());
        const data = Object.fromEntries(new FormData(event.target));
        data.departure_at = `${data.departure_at}:00`;
        if (!editingId) delete data.status;
        const wasEditing = editingId !== null;
        await api(wasEditing ? `/trips/${editingId}` : '/trips', wasEditing ? 'PATCH' : 'POST', data);
        resetEditor();
        await loadTrips();
        notify(wasEditing ? 'Cambios guardados correctamente.' : 'Viaje registrado correctamente.');
    });
});

// Esperamos a que el usuario deje de escribir antes de consultar el backend.
function enableAddressSearch(field) {
    const input = byId(field);
    const list = byId(`${field}-addresses`);
    const hint = byId(`${field}-hint`);
    let timer;
    let controller;
    let version = 0;

    function reset() {
        version++;
        clearTimeout(timer);
        controller?.abort();
        list.replaceChildren();
        list.hidden = true;
        hint.textContent = 'Escribe al menos 3 letras para buscar direcciones de Perú.';
    }

    input.addEventListener('input', () => {
        reset();
        const query = input.value.trim();
        if (query.length < 3 || !token) return;
        if (query.length > 255) {
            hint.textContent = 'Puedes escribir la dirección manualmente.';
            return;
        }
        const currentVersion = version;
        hint.textContent = 'Buscando direcciones…';
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const response = await fetch(`/api/addresses?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
                    signal: controller.signal,
                });
                const result = await response.json();
                if (currentVersion !== version) return;
                if (!response.ok) {
                    throw new Error(result.message || 'Sugerencias no disponibles. Puedes escribir la dirección manualmente.');
                }
                if (!Array.isArray(result)) throw new Error('Sugerencias no disponibles. Puedes escribir la dirección manualmente.');
                for (const address of result) {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.textContent = address.label;
                    option.addEventListener('click', () => {
                        input.value = address.label;
                        reset();
                        hint.textContent = 'Dirección seleccionada.';
                        input.focus();
                    });
                    list.append(option);
                }
                list.hidden = result.length === 0;
                hint.textContent = result.length ? 'Selecciona una sugerencia o escribe la dirección.' : 'No encontramos coincidencias. Puedes escribir la dirección manualmente.';
            } catch (error) {
                if (currentVersion !== version || error.name === 'AbortError') return;
                hint.textContent = error instanceof TypeError ? 'Sin conexión. Puedes escribir la dirección manualmente.' : error.message;
            }
        }, 400);
    });
    return { reset };
}

addressSearches.push(enableAddressSearch('origin'), enableAddressSearch('destination'));
