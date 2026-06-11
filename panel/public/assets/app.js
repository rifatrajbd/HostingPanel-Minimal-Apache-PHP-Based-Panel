// HostingPanel helpers (CSP-safe: no inline scripts allowed).

// Compress panel posts its own form; pull the checked items from the bulk list.
function hpCopySelection(form) {
    const slot = form.querySelector('.selection-slot');
    if (!slot) return;
    slot.innerHTML = '';
    document.querySelectorAll('.fsel:checked').forEach(cb => {
        const i = document.createElement('input');
        i.type = 'hidden';
        i.name = 'items[]';
        i.value = cb.value;
        slot.appendChild(i);
    });
}

function hpToggleAll(master) {
    document.querySelectorAll('.fsel').forEach(c => { c.checked = master.checked; });
}
