import { useMemo, useState } from "react";
import {
  KeyboardAvoidingView,
  Modal,
  Platform,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useApp } from "../contexts/AppContext";
import { useRtl } from "../lib/rtl";

export type SelectOption = { value: string; label: string };

type Props = {
  label?: string;
  placeholder?: string;
  value: string;
  options: SelectOption[];
  onChange: (value: string, option: SelectOption) => void;
  disabled?: boolean;
  emptyText?: string;
  /** Enable search filter (default true). */
  searchable?: boolean;
};

/** Picker modal for country / state / district with optional search. */
export function SelectField({
  label,
  placeholder,
  value,
  options,
  onChange,
  disabled,
  emptyText,
  searchable = true,
}: Props) {
  const { accent, t } = useApp();
  const { textAlign, writingDirection } = useRtl();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const selected = useMemo(
    () => options.find((o) => o.value === value),
    [options, value],
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return options;
    return options.filter(
      (o) =>
        o.label.toLowerCase().includes(q) ||
        o.value.toLowerCase().includes(q),
    );
  }, [options, query]);

  return (
    <View style={[styles.wrap, !label && styles.wrapTight]}>
      {label ? (
        <Text style={[styles.label, { textAlign, writingDirection }]}>{label}</Text>
      ) : null}
      <Pressable
        style={[styles.field, disabled && styles.disabled]}
        disabled={disabled || options.length === 0}
        onPress={() => {
          setQuery("");
          setOpen(true);
        }}
      >
        <Text
          style={[
            selected ? styles.value : styles.placeholder,
            { textAlign, writingDirection },
          ]}
          numberOfLines={1}
        >
          {selected?.label ||
            placeholder ||
            emptyText ||
            t("forms.select")}
        </Text>
      </Pressable>

      <Modal visible={open} animationType="slide" transparent>
        <KeyboardAvoidingView
          style={styles.backdrop}
          behavior={Platform.OS === "ios" ? "padding" : undefined}
        >
          <View style={styles.sheet}>
            <Text style={[styles.sheetTitle, { textAlign, writingDirection }]}>
              {label || placeholder || t("forms.select")}
            </Text>
            {searchable ? (
              <TextInput
                style={[styles.search, { textAlign, writingDirection, color: "#111" }]}
                value={query}
                onChangeText={setQuery}
                placeholder={t("forms.search")}
                placeholderTextColor="#888"
                autoFocus
                autoCapitalize="none"
                clearButtonMode="while-editing"
                underlineColorAndroid="transparent"
              />
            ) : null}
            <FlatList
              data={filtered}
              keyExtractor={(item) => item.value}
              keyboardShouldPersistTaps="handled"
              style={{ maxHeight: 380 }}
              renderItem={({ item }) => {
                const active = item.value === value;
                return (
                  <Pressable
                    style={[
                      styles.option,
                      active && {
                        backgroundColor: "#fff8e8",
                        borderColor: accent,
                      },
                    ]}
                    onPress={() => {
                      onChange(item.value, item);
                      setOpen(false);
                      setQuery("");
                    }}
                  >
                    <Text
                      style={[styles.optionText, { textAlign, writingDirection }]}
                    >
                      {item.label}
                    </Text>
                  </Pressable>
                );
              }}
              ListEmptyComponent={
                <Text style={styles.empty}>
                  {emptyText || t("forms.noOptions")}
                </Text>
              }
            />
            <Pressable
              style={styles.cancel}
              onPress={() => {
                setOpen(false);
                setQuery("");
              }}
            >
              <Text style={styles.cancelText}>{t("common.cancel")}</Text>
            </Pressable>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 10 },
  wrapTight: { marginBottom: 0 },
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
  search: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 12,
    fontSize: 16,
    color: "#111",
  },
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
