import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { AvailabilityModal } from "../components/catalog/AvailabilityModal";
import { fetchAvailability } from "../lib/api";
import type { ProductAvailability } from "../lib/types";
import { useApp } from "./AppContext";

type AvailabilityModalContextValue = {
  openAvailability: (productId: number, variationId: number) => void;
};

const AvailabilityModalContext =
  createContext<AvailabilityModalContextValue | null>(null);

/**
 * Single shared availability sheet so catalog cards do not each mount a Modal.
 */
export function AvailabilityModalProvider({
  children,
}: {
  children: ReactNode;
}) {
  const { locale, t } = useApp();
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [availability, setAvailability] = useState<ProductAvailability | null>(
    null,
  );

  const openAvailability = useCallback(
    (productId: number, variationId: number) => {
      setOpen(true);
      setLoading(true);
      setError(null);
      setAvailability(null);
      void (async () => {
        try {
          const { data } = await fetchAvailability(
            productId,
            variationId,
            locale,
          );
          setAvailability(data);
        } catch (e) {
          setError(
            e instanceof Error ? e.message : t("availability.loadError"),
          );
        } finally {
          setLoading(false);
        }
      })();
    },
    [locale, t],
  );

  const value = useMemo(
    () => ({ openAvailability }),
    [openAvailability],
  );

  return (
    <AvailabilityModalContext.Provider value={value}>
      {children}
      <AvailabilityModal
        open={open}
        loading={loading}
        error={error}
        availability={availability}
        onClose={() => setOpen(false)}
      />
    </AvailabilityModalContext.Provider>
  );
}

export function useAvailabilityModal(): AvailabilityModalContextValue {
  const ctx = useContext(AvailabilityModalContext);
  if (!ctx) {
    throw new Error(
      "useAvailabilityModal must be used within AvailabilityModalProvider",
    );
  }
  return ctx;
}
