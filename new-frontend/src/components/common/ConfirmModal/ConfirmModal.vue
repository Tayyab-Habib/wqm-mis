<script setup>
defineProps({
  modelValue:   { type: Boolean, default: false },
  title:        { type: String, default: 'Confirm' },
  message:      { type: String, default: 'Are you sure?' },
  confirmText:  { type: String, default: 'Confirm' },
  cancelText:   { type: String, default: 'Cancel' },
  // 'danger' (red) for destructive actions, 'primary' (blue) for everything else.
  variant:      { type: String, default: 'danger' },
  busy:         { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

function onCancel() {
  emit('update:modelValue', false)
  emit('cancel')
}
function onConfirm() {
  emit('confirm')
  // Parent decides when to close — busy state can keep the modal open
  // while the action is in flight. If the parent doesn't toggle busy,
  // modelValue flips false automatically on the next emit.
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue"
         role="dialog"
         aria-modal="true"
         @click.self="onCancel"
         style="position:fixed;inset:0;background:rgba(15,23,42,.62);z-index:9998;display:flex;align-items:center;justify-content:center;padding:24px">
      <div style="background:#fff;border-radius:8px;width:100%;max-width:440px;box-shadow:0 12px 48px rgba(0,0,0,.32);overflow:hidden">
        <header style="padding:14px 18px;border-bottom:1px solid #e5e7eb">
          <h3 style="margin:0;font-size:15px;font-weight:700;color:#1a2e4a">{{ title }}</h3>
        </header>
        <div style="padding:18px;font-size:13px;color:#334155;line-height:1.5">
          {{ message }}
        </div>
        <footer style="display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid #e5e7eb;background:#f9fafb">
          <button type="button"
                  :disabled="busy"
                  @click="onCancel"
                  style="background:#fff;color:#334155;border:1px solid #cbd5e1;border-radius:5px;padding:6px 14px;font-size:12.5px;font-weight:500;font-family:inherit;cursor:pointer">
            {{ cancelText }}
          </button>
          <button type="button"
                  :disabled="busy"
                  @click="onConfirm"
                  :style="`background:${variant === 'danger' ? '#dc2626' : '#1d4ed8'};color:#fff;border:none;border-radius:5px;padding:6px 14px;font-size:12.5px;font-weight:600;font-family:inherit;cursor:pointer;opacity:${busy ? 0.6 : 1}`">
            {{ busy ? 'Working…' : confirmText }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
