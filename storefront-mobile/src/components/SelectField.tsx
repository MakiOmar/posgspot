import { useMemo, useState } from "react";
import {
  FlatList,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useApp } from "../contexts/AppContext";

export type SelectOption = { value: string; label: string };

type Props = {
  label: string;
  placeholder?: string;
  value: string;
  options: SelectOption[];
  onChange: (value: string, option: SelectOption) => void;
  disabled?: boolean;
  emptyText?: string;
};

/** Simple searchable-free picker modal for country / state / district. */
export function SelectField({
  label,
  placeholder,
  value,
  options,
  onChange,
  disabled,
  emptyText,
}: Props) {
  const { accent, t } = useApp();
  const [open, setOpen] = useState(false);
  const selected = useMemo(
    () => options.find((o) => o.value === value),
    [options, value],
  );

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <Pressable
        style={[styles.field, disabled && styles.disabled]}
        disabled={disabled || options.length === 0}
        onPress={() => setOpen(true)}
      >
        <Text style={selected ? styles.value : styles.placeholder}>
          {selected?.label ||
            placeholder ||
            emptyText ||
            t("forms.select")}
        </Text>
      </Pressable>

      <Modal visible={open} animationType="slide" transparent>
        <View style={styles.backdrop}>
          <View style={styles.sheet}>
            <Text style={styles.sheetTitle}>{label}</Text>
            <FlatList
              data={options}
              keyExtractor={(item) => item.value}
              style={{ maxHeight: 420 }}
              renderItem={({ item }) => {
                const active = item.value === value;
                return (
                  <Pressable
                    style={[
                      styles.option,
                      active && { backgroundColor: "#fff8e8", borderColor: accent },
                    ]}
                    onPress={() => {
                      onChange(item.value, item);
                      setOpen(false);
                    }}
                  >
                    <Text style={styles.optionText}>{item.label}</Text>
                  </Pressable>
                );
              }}
              ListEmptyComponent={
                <Text style={styles.empty}>{emptyText || t("forms.noOptions")}</Text>
              }
            />
            <Pressable style={styles.cancel} onPress={() => setOpen(false)}>
              <Text style={styles.cancelText}>{t("common.cancel")}</Text>
            </Pressable>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 10 },
  label: { fontWeight: "700", marginBottom: 6, color: "#222" },
  field: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 14,
  },
  disabled: { opacity: 0.5 },
  value: { color: "#111", fontSize: 16 },
  placeholder: { color: "#999", fontSize: 16 },
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "flex-end",
  },
  sheet: {
    backgroundColor: "#F7F7F5",
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    padding: 16,
    paddingBottom: 28,
  },
  sheetTitle: { fontSize: 18, fontWeight: "800", marginBottom: 12 },
  option: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    padding: 14,
    marginBottom: 8,
  },
  optionText: { fontSize: 16, color: "#111" },
  empty: { textAlign: "center", color: "#666", padding: 20 },
  cancel: { alignItems: "center", paddingVertical: 12 },
  cancelText: { fontWeight: "700", color: "#333" },
});
