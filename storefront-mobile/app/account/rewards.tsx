import { useCallback, useEffect, useState } from "react";
import { ScrollView, StyleSheet } from "react-native";
import { Redirect } from "expo-router";
import { fetchRewardPoints } from "../../src/lib/api";
import type { RewardPointsBalance } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { RewardPointsPanel } from "../../src/components/account/RewardPointsPanel";
import {
  ErrorBlock,
  LoadingBlock,
  Screen,
} from "../../src/components/ui";

export default function RewardsScreen() {
  const { token, t } = useApp();
  const [data, setData] = useState<RewardPointsBalance | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) {
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const res = await fetchRewardPoints(token);
      setData(res.data);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [token, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        {error ? (
          <ErrorBlock message={error} onRetry={() => void load()} />
        ) : null}
        <RewardPointsPanel data={data} />
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, paddingBottom: 40 },
});
