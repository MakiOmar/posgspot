type ToastKind = "success" | "error" | "info";

export type ToastPayload = {
  id: string;
  kind: ToastKind;
  title: string;
  message?: string;
  durationMs: number;
};

type Listener = (toast: ToastPayload) => void;

const listeners = new Set<Listener>();

function emit(payload: ToastPayload) {
  listeners.forEach((listener) => listener(payload));
}

function show(
  kind: ToastKind,
  title: string,
  message?: string,
  durationMs = 2800,
) {
  const trimmedTitle = title.trim();
  const trimmedMessage = message?.trim();
  if (!trimmedTitle && !trimmedMessage) return;

  emit({
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    kind,
    title: trimmedTitle || trimmedMessage || "",
    message:
      trimmedTitle && trimmedMessage && trimmedMessage !== trimmedTitle
        ? trimmedMessage
        : undefined,
    durationMs,
  });
}

/** Imperative toast API — use for success/error feedback instead of Alert.alert. */
export const toast = {
  success(title: string, message?: string) {
    show("success", title, message);
  },
  error(title: string, message?: string) {
    show("error", title, message, 3600);
  },
  info(title: string, message?: string) {
    show("info", title, message);
  },
  subscribe(listener: Listener) {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  },
};
