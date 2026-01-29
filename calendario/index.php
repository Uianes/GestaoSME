<?php
require_once __DIR__ . '/../auth/permissions.php';
require_once __DIR__ . '/../config/db.php';

if (!user_can_access_system('calendario')) {
    ?>
    <!doctype html>
    <html lang="pt-br">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
      <title>Calendário</title>
    </head>
    <body class="bg-light">
      <div class="container py-4">
        <div class="alert alert-danger" role="alert">Sem permissão de acesso.</div>
      </div>
    </body>
    </html>
    <?php
    return;
}

$conn = db();
$userMatricula = (int)($_SESSION['user']['matricula'] ?? 0);
$isAdmin = !empty($_SESSION['user']['adm']) && (int)$_SESSION['user']['adm'] === 1;

$usuarios = [];
$result = $conn->query('SELECT matricula, nome FROM usuarios WHERE ativo = 1 ORDER BY nome');
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

$unidades = [];
$result = $conn->query('SELECT id_unidade, nome FROM unidade ORDER BY nome');
while ($row = $result->fetch_assoc()) {
    $unidades[] = $row;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calendário</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
  <style>
    :root {
      color-scheme: light;
    }
    body {
      background: #f6f8fb;
    }
    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .page-title {
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .calendar-card {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 16px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    .fc .fc-toolbar-title {
      font-size: 1.15rem;
      font-weight: 600;
      text-transform: capitalize;
    }
    .fc .fc-toolbar {
      flex-wrap: wrap;
      gap: 0.75rem;
    }
    .fc .fc-toolbar-chunk {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    .fc .fc-button-group {
      flex-wrap: wrap;
    }
    .fc .fc-button {
      border-radius: 999px;
      padding: 0.4rem 0.9rem;
      font-weight: 600;
    }
    .fc .fc-button-primary {
      background: #1f3b8c;
      border-color: #1f3b8c;
    }
    .fc .fc-button-primary:hover {
      background: #182f70;
      border-color: #182f70;
    }
    .fc .fc-daygrid-day-number {
      font-weight: 600;
      color: #1f2937;
    }
    .modal-content {
      border-radius: 16px;
    }
    .form-select[multiple] {
      min-height: 240px;
    }
    @media (max-width: 768px) {
      .fc .fc-toolbar-title {
        font-size: 1rem;
      }
      .fc .fc-button {
        padding: 0.35rem 0.7rem;
        font-size: 0.85rem;
      }
    }
  </style>
</head>
<body>
  <div class="container-fluid py-4 px-4">
    <div class="page-header">
      <div>
        <h2 class="page-title mb-1">Calendário</h2>
        <p class="text-muted mb-0">Crie e gerencie eventos para usuários e escolas.</p>
      </div>
      <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Clique e arraste para criar ou mover eventos.
      </div>
    </div>

    <div class="card calendar-card">
      <div class="card-body">
        <div id="calendar"></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventModalLabel">Evento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="eventError"></div>
        <form id="eventForm">
          <input type="hidden" id="eventId">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Título</label>
              <input class="form-control" id="eventTitle" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Local</label>
              <input class="form-control" id="eventLocation">
            </div>
            <div class="col-md-6">
              <label class="form-label">Início</label>
              <input type="datetime-local" class="form-control" id="eventStart" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Fim</label>
              <input type="datetime-local" class="form-control" id="eventEnd">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="eventAllDay">
                <label class="form-check-label" for="eventAllDay">Dia inteiro</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" id="eventDescription" rows="3"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Usuários</label>
              <div class="border rounded p-2" style="max-height: 240px; overflow: auto;">
                <?php foreach ($usuarios as $usuario): ?>
                  <div class="form-check">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="eventUsers[]"
                      value="<?= (int)$usuario['matricula'] ?>"
                      id="user-<?= (int)$usuario['matricula'] ?>"
                    >
                    <label class="form-check-label" for="user-<?= (int)$usuario['matricula'] ?>">
                      <?= htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Escolas/Unidades</label>
              <div class="border rounded p-2" style="max-height: 240px; overflow: auto;">
                <?php foreach ($unidades as $unidade): ?>
                  <div class="form-check">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="eventUnits[]"
                      value="<?= (int)$unidade['id_unidade'] ?>"
                      id="unit-<?= (int)$unidade['id_unidade'] ?>"
                    >
                    <label class="form-check-label" for="unit-<?= (int)$unidade['id_unidade'] ?>">
                      <?= htmlspecialchars($unidade['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto d-none" id="deleteEventBtn">Excluir</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="saveEventBtn">Salvar</button>
      </div>
    </div>
  </div>
</div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
  <script>
  const calendarEl = document.getElementById('calendar');
  const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
  const eventForm = document.getElementById('eventForm');
  const eventError = document.getElementById('eventError');
  const deleteBtn = document.getElementById('deleteEventBtn');
  const saveBtn = document.getElementById('saveEventBtn');

  const currentUser = <?= (int)$userMatricula ?>;
  const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

  function showError(message) {
    eventError.textContent = message;
    eventError.classList.remove('d-none');
  }

  function clearError() {
    eventError.classList.add('d-none');
    eventError.textContent = '';
  }

  function toLocalInputValue(date) {
    const pad = (value) => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }

  function setReadOnly(readOnly) {
    document.querySelectorAll('#eventForm input, #eventForm textarea, #eventForm select').forEach((el) => {
      el.disabled = readOnly;
    });
    saveBtn.classList.toggle('d-none', readOnly);
    deleteBtn.classList.toggle('d-none', readOnly);
  }

  function clearUserCheckboxes() {
    document.querySelectorAll('input[name="eventUsers[]"]').forEach((checkbox) => {
      checkbox.checked = false;
    });
  }

  function clearUnitCheckboxes() {
    document.querySelectorAll('input[name="eventUnits[]"]').forEach((checkbox) => {
      checkbox.checked = false;
    });
  }

  async function fetchDetails(eventId) {
    const response = await fetch('actions/calendar_events.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'details', id: eventId })
    });
    if (!response.ok) {
      return null;
    }
    return await response.json();
  }

  async function saveEvent(payload) {
    const response = await fetch('actions/calendar_events.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'Erro ao salvar evento.');
    }
    return data;
  }

  const calendar = new FullCalendar.Calendar(calendarEl, {
    locale: 'pt-br',
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
    },
    buttonText: {
      today: 'Hoje',
      month: 'Mês',
      week: 'Semana',
      day: 'Dia',
      list: 'Lista'
    },
    allDayText: 'O dia todo',
    selectable: true,
    editable: true,
    eventOverlap: true,
    events: 'actions/calendar_events.php',
    select: function(info) {
      clearError();
      eventForm.reset();
      document.getElementById('eventId').value = '';
      document.getElementById('eventTitle').value = '';
      document.getElementById('eventLocation').value = '';
      document.getElementById('eventDescription').value = '';
      document.getElementById('eventAllDay').checked = info.allDay;
      document.getElementById('eventStart').value = toLocalInputValue(info.start);
      if (info.end) {
        const endDate = info.allDay ? new Date(info.end.getTime() - 86400000) : info.end;
        document.getElementById('eventEnd').value = toLocalInputValue(endDate);
      } else {
        document.getElementById('eventEnd').value = '';
      }
      clearUserCheckboxes();
      clearUnitCheckboxes();
      setReadOnly(false);
      deleteBtn.classList.add('d-none');
      eventModal.show();
    },
    eventClick: async function(info) {
      clearError();
      const event = info.event;
      const canEdit = isAdmin || event.extendedProps.criado_por === currentUser;
      document.getElementById('eventId').value = event.id;
      document.getElementById('eventTitle').value = event.title;
      document.getElementById('eventLocation').value = event.extendedProps.local || '';
      document.getElementById('eventDescription').value = event.extendedProps.descricao || '';
      document.getElementById('eventAllDay').checked = event.allDay;
      document.getElementById('eventStart').value = toLocalInputValue(event.start);
      document.getElementById('eventEnd').value = event.end ? toLocalInputValue(event.end) : '';
      clearUserCheckboxes();
      clearUnitCheckboxes();

      const details = await fetchDetails(event.id);
      if (details && details.ok) {
        details.usuarios.forEach((id) => {
          const checkbox = document.getElementById(`user-${id}`);
          if (checkbox) checkbox.checked = true;
        });
        details.unidades.forEach((id) => {
          const checkbox = document.getElementById(`unit-${id}`);
          if (checkbox) checkbox.checked = true;
        });
      }

      setReadOnly(!canEdit);
      eventModal.show();
    },
    eventDrop: function(info) {
      if (!isAdmin && info.event.extendedProps.criado_por !== currentUser) {
        info.revert();
        return;
      }
      saveEvent({
        action: 'move',
        id: info.event.id,
        inicio: info.event.start,
        fim: info.event.end,
        allDay: info.event.allDay
      }).catch(() => {
        info.revert();
      });
    },
    eventResize: function(info) {
      if (!isAdmin && info.event.extendedProps.criado_por !== currentUser) {
        info.revert();
        return;
      }
      saveEvent({
        action: 'move',
        id: info.event.id,
        inicio: info.event.start,
        fim: info.event.end,
        allDay: info.event.allDay
      }).catch(() => {
        info.revert();
      });
    },
    eventAllow: function(dropInfo, draggedEvent) {
      return isAdmin || draggedEvent.extendedProps.criado_por === currentUser;
    }
  });

  calendar.render();

  saveBtn.addEventListener('click', async () => {
    clearError();
    const eventId = document.getElementById('eventId').value;
    const payload = {
      action: eventId ? 'update' : 'create',
      id: eventId ? parseInt(eventId, 10) : undefined,
      titulo: document.getElementById('eventTitle').value.trim(),
      descricao: document.getElementById('eventDescription').value.trim(),
      local: document.getElementById('eventLocation').value.trim(),
      inicio: document.getElementById('eventStart').value,
      fim: document.getElementById('eventEnd').value,
      allDay: document.getElementById('eventAllDay').checked,
      usuarios: Array.from(document.querySelectorAll('input[name=\"eventUsers[]\"]:checked')).map(opt => opt.value),
      unidades: Array.from(document.querySelectorAll('input[name="eventUnits[]"]:checked')).map(opt => opt.value)
    };

    try {
      await saveEvent(payload);
      eventModal.hide();
      calendar.refetchEvents();
    } catch (error) {
      showError(error.message);
    }
  });

  deleteBtn.addEventListener('click', async () => {
    const eventId = document.getElementById('eventId').value;
    if (!eventId) return;
    if (!confirm('Deseja excluir este evento?')) return;
    try {
      await saveEvent({ action: 'delete', id: parseInt(eventId, 10) });
      eventModal.hide();
      calendar.refetchEvents();
    } catch (error) {
      showError(error.message);
    }
  });
  </script>
</body>
</html>
