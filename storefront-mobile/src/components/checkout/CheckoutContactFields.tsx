import { StyleSheet, Text } from "react-native";
import { LabeledInput } from "../LabeledInput";
import { useApp } from "../../contexts/AppContext";

type Props = {
  firstName: string;
  lastName: string;
  email: string;
  mobile: string;
  onFirstNameChange: (value: string) => void;
  onLastNameChange: (value: string) => void;
  onEmailChange: (value: string) => void;
  onMobileChange: (value: string) => void;
};

/** Checkout contact / customer identity fields. */
export function CheckoutContactFields({
  firstName,
  lastName,
  email,
  mobile,
  onFirstNameChange,
  onLastNameChange,
  onEmailChange,
  onMobileChange,
}: Props) {
  const { t } = useApp();

  return (
    <>
      <Text style={styles.section}>{t("checkout.contact")}</Text>
      <LabeledInput
        label={t("checkout.firstName")}
        value={firstName}
        onChangeText={onFirstNameChange}
      />
      <LabeledInput
        label={t("checkout.lastName")}
        value={lastName}
        onChangeText={onLastNameChange}
      />
      <LabeledInput
        label={t("checkout.email")}
        value={email}
        onChangeText={onEmailChange}
        autoCapitalize="none"
        keyboardType="email-address"
      />
      <LabeledInput
        label={t("checkout.mobile")}
        value={mobile}
        onChangeText={onMobileChange}
        keyboardType="phone-pad"
      />
    </>
  );
}

const styles = StyleSheet.create({
  section: { fontWeight: "800", fontSize: 16, marginTop: 8, marginBottom: 10 },
});
