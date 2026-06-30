/** Lazy-loaded SweetAlert2 helpers for account/auth feedback (client-only). */

type SwalModule = typeof import("sweetalert2").default;

let swalModulePromise: Promise<SwalModule> | null = null;
let cssLoaded = false;

async function loadSweetAlertCss(): Promise<void> {
  if (cssLoaded || typeof document === "undefined") {
    return;
  }
  await import("sweetalert2/dist/sweetalert2.min.css");
  cssLoaded = true;
}

async function getSwal(): Promise<SwalModule> {
  if (!swalModulePromise) {
    swalModulePromise = (async () => {
      await loadSweetAlertCss();
      const mod = await import("sweetalert2");
      return mod.default;
    })();
  }
  return swalModulePromise;
}

const toastBase = {
  toast: true,
  position: "top-end" as const,
  showConfirmButton: false,
  timer: 3500,
  timerProgressBar: true,
};

export async function toastSuccess(message: string): Promise<void> {
  const Swal = await getSwal();
  await Swal.fire({ ...toastBase, icon: "success", title: message });
}

export async function toastError(message: string): Promise<void> {
  const Swal = await getSwal();
  await Swal.fire({ ...toastBase, icon: "error", title: message, timer: 4500 });
}

export async function toastInfo(message: string): Promise<void> {
  const Swal = await getSwal();
  await Swal.fire({ ...toastBase, icon: "info", title: message });
}

/** SweetAlert2 confirmation dialog — returns true when the user confirms. */
export async function confirmAction(options: {
  title: string;
  text?: string;
  confirmText?: string;
  cancelText?: string;
  icon?: "warning" | "question";
}): Promise<boolean> {
  const Swal = await getSwal();
  const result = await Swal.fire({
    title: options.title,
    text: options.text,
    icon: options.icon ?? "question",
    showCancelButton: true,
    confirmButtonText: options.confirmText ?? "Confirm",
    cancelButtonText: options.cancelText ?? "Cancel",
    confirmButtonColor: "#00d4aa",
    reverseButtons: true,
    focusCancel: true,
  });
  return result.isConfirmed === true;
}

export async function confirmSignOut(): Promise<boolean> {
  return confirmAction({
    title: "Sign out?",
    text: "You will need to sign in again to access your account.",
    confirmText: "Sign out",
    cancelText: "Stay signed in",
    icon: "warning",
  });
}
