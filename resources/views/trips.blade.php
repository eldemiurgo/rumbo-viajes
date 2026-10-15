<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rumbo · Mis viajes</title>
    <link rel="stylesheet" href="{{ asset('trips.css') }}">
    <script src="{{ asset('trips.js') }}" defer></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('trips') }}"><span class="brand-icon" aria-hidden="true">↗</span> rumbo<span class="brand-dot">.</span></a>
        <span class="top-caption">Tu próxima parada empieza aquí</span>
        <div id="account" class="account" hidden><span id="user-name"></span><button id="logout" class="button secondary small" type="button">Cerrar sesión</button></div>
    </header>
    <main>
        <div id="notice" class="notice" role="status" aria-live="polite" hidden></div>
        <section id="auth-view" class="auth-layout">
            <div class="intro">
                <span class="eyebrow">MENOS VUELTAS, MÁS VIAJES</span>
                <h1>Todos tus viajes.<br><span>Un solo lugar.</span></h1>
                <p>Organiza tus rutas, consulta tus próximos destinos y mantén cada detalle a mano.</p>
                <div class="route-card" aria-hidden="true"><div class="route-heading"><span>EL CAMINO LO ELIGES TÚ</span><span>↗</span></div><div class="route-line"><span class="route-point"></span><span class="route-dash"></span><span class="route-point end"></span></div><div class="route-addresses"><span>Tu origen</span><span>Tu próximo destino</span></div></div>
                <div class="intro-footer"><span class="check">✓</span> Tus viajes, en tu propia cuenta.</div>
            </div>
            <div class="auth-card">
                <div class="tabs" role="group" aria-label="Acceso a la cuenta"><button id="login-tab" class="tab active" type="button" aria-pressed="true">Iniciar sesión</button><button id="register-tab" class="tab" type="button" aria-pressed="false">Crear cuenta</button></div>
                <form id="login-form">
                    <h2>Qué bueno verte.</h2><p class="muted">Ingresa para administrar tus viajes.</p>
                    <label for="login-email">Correo electrónico</label><input id="login-email" name="email" type="email" autocomplete="username" placeholder="tu@correo.com" required maxlength="255">
                    <label for="login-password">Contraseña</label><input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Tu contraseña" required>
                    <button class="button primary full" type="submit">Entrar a mis viajes <span aria-hidden="true">→</span></button>
                </form>
                <form id="register-form" hidden>
                    <h2>Empieza tu recorrido.</h2><p class="muted">Crea tu cuenta para organizar tus viajes.</p>
                    <label for="register-name">Nombre</label><input id="register-name" name="name" autocomplete="name" required maxlength="255">
                    <label for="register-email">Correo electrónico</label><input id="register-email" name="email" type="email" autocomplete="username" required maxlength="255">
                    <label for="register-password">Contraseña</label><input id="register-password" name="password" type="password" autocomplete="new-password" required minlength="8" placeholder="Mínimo 8 caracteres">
                    <label for="register-confirmation">Repetir contraseña</label><input id="register-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
                    <button class="button primary full" type="submit">Crear mi cuenta <span aria-hidden="true">→</span></button>
                </form>
            </div>
        </section>
        <section id="dashboard" hidden>
            <div class="page-heading"><div><span class="eyebrow">TU ESPACIO DE VIAJES</span><h1>Mis viajes<span class="brand-dot">.</span></h1><p class="muted">Cada ruta, con sus detalles en orden.</p></div><div class="counter"><strong id="trip-count">0</strong><span>viajes registrados</span></div></div>
            <div class="dashboard-grid">
                <section class="panel editor"><div class="panel-title"><span class="step-icon" aria-hidden="true">＋</span><h2 id="editor-title">Nuevo viaje</h2></div><p class="muted" id="editor-description">Elige el destino. Nosotros guardamos los detalles.</p>
                    <form id="trip-form">
                        <label for="origin">Dirección de recogida</label><input id="origin" name="origin" required maxlength="255" placeholder="Ej. Av. Arequipa 1234, Lima" autocomplete="off" aria-describedby="origin-hint"><div id="origin-addresses" class="city-suggestions" role="group" aria-label="Sugerencias de recogida" hidden></div><small id="origin-hint" class="city-hint" role="status">Escribe al menos 3 letras para buscar direcciones de Perú.</small>
                        <label for="destination">Dirección de destino</label><input id="destination" name="destination" required maxlength="255" placeholder="Ej. Aeropuerto Jorge Chávez, Callao" autocomplete="off" aria-describedby="destination-hint"><div id="destination-addresses" class="city-suggestions" role="group" aria-label="Sugerencias de destino" hidden></div><small id="destination-hint" class="city-hint" role="status">Escribe al menos 3 letras para buscar direcciones de Perú.</small>
                        <p class="city-credit">Sugerencias de <a href="https://www.geoapify.com/" target="_blank" rel="noopener noreferrer">Geoapify</a> · <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a></p>
                        <label for="departure_at">Fecha y hora de salida (UTC)</label><input id="departure_at" name="departure_at" type="datetime-local" required>
                        <label for="price">Precio</label><input id="price" name="price" type="number" required min="0" max="99999999.99" step="0.01" placeholder="0.00">
                        <div id="status-field" hidden><label for="status">Estado</label><select id="status" name="status"><option value="pending">Pendiente</option><option value="completed">Completado</option><option value="cancelled">Cancelado</option></select></div>
                        <button id="save-trip" class="button primary full" type="submit">Guardar viaje</button><button id="cancel-edit" class="button secondary full" type="button" hidden>Cancelar edición</button>
                    </form>
                </section>
                <section class="panel trip-list"><div class="list-heading"><div><h2>Tus rutas</h2><p class="muted">Consulta y administra tus viajes.</p></div><button id="refresh" class="button secondary small" type="button">Actualizar lista</button></div>
                    <div id="empty" class="empty" hidden><span class="empty-icon" aria-hidden="true">↗</span><h3>Tu próxima ruta empieza aquí</h3><p>Registra tu primer viaje con el formulario.</p></div>
                    <div id="table-wrap" class="table-wrap" hidden><table><thead><tr><th scope="col">Recogida → destino</th><th scope="col">Salida · UTC</th><th scope="col">Precio</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody id="trips-body"></tbody></table></div>
                </section>
            </div>
        </section>
    </main>
    <footer>Rumbo · Un lugar para tus próximos destinos.</footer>
</body>
</html>
