import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  RefreshControl,
  StyleSheet,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as mining from '../../api/mining';
import { useAuthStore } from '../../store/authStore';
import { formatCurrency, formatGC } from '../../utils/helpers';

export default function MiningScreen({ navigation }) {
  const user = useAuthStore(state => state.user);
  const [dailyBonus, setDailyBonus] = useState(null);
  const [plans, setPlans] = useState([]);
  const [stats, setStats] = useState(null);
  const [activePackages, setActivePackages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [claiming, setClaiming] = useState(false);
  const [activatingPlan, setActivatingPlan] = useState(null);
  const [claimingPlan, setClaimingPlan] = useState(null);

  const fetchData = useCallback(async () => {
    try {
      const bonusRes = await mining.getDailyBonus();
      setDailyBonus(bonusRes.data.data || bonusRes.data);

      if (user?.role === 'member') {
        const [plansRes, statsRes] = await Promise.all([
          mining.getMiningPlans(),
          mining.getMiningStats(),
        ]);
        setPlans(plansRes.data.data || plansRes.data);
        const statsData = statsRes.data.data || statsRes.data;
        setStats(statsData);
        setActivePackages(statsData.active_packages || []);
      }
    } catch (err) {
      Alert.alert('Error', err.response?.data?.message || 'Gagal memuat data mining');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [user]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  const handleClaimDailyBonus = async () => {
    setClaiming(true);
    try {
      await mining.claimDailyBonus();
      Alert.alert('Berhasil', 'Bonus harian berhasil diklaim!');
      fetchData();
      useAuthStore.getState().fetchUser();
    } catch (err) {
      Alert.alert('Error', err.response?.data?.message || 'Gagal klaim bonus');
    } finally {
      setClaiming(false);
    }
  };

  const handleActivatePlan = (plan) => {
    Alert.alert(
      'Konfirmasi',
      `Deposit ${formatGC(plan.deposit)} untuk mengaktifkan paket "${plan.name}"?`,
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Aktifkan',
          onPress: async () => {
            setActivatingPlan(plan.id);
            try {
              await mining.activateMiningPlan(plan.id);
              Alert.alert('Berhasil', 'Paket mining berhasil diaktifkan!');
              fetchData();
              useAuthStore.getState().fetchUser();
            } catch (err) {
              Alert.alert('Error', err.response?.data?.message || 'Gagal mengaktifkan paket');
            } finally {
              setActivatingPlan(null);
            }
          },
        },
      ]
    );
  };

  const handleClaimMining = async (planId) => {
    setClaimingPlan(planId);
    try {
      await mining.claimMining(planId);
      Alert.alert('Berhasil', 'Klaim mining berhasil!');
      fetchData();
      useAuthStore.getState().fetchUser();
    } catch (err) {
      Alert.alert('Error', err.response?.data?.message || 'Gagal klaim mining');
    } finally {
      setClaimingPlan(null);
    }
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#f59e0b" />
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
    >
      {/* Daily Bonus Section */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Ionicons name="gift-outline" size={24} color="#f59e0b" />
          <Text style={styles.cardTitle}>Bonus Harian</Text>
        </View>
        {dailyBonus && (
          <>
            <Text style={styles.bonusAmount}>
              {formatGC(dailyBonus.bonus_amount)}
            </Text>
            {dailyBonus.claimed_today ? (
              <View style={styles.claimedBadge}>
                <Ionicons name="checkmark-circle" size={18} color="#10b981" />
                <Text style={styles.claimedText}>Sudah diklaim hari ini</Text>
              </View>
            ) : (
              <TouchableOpacity
                style={styles.claimButton}
                onPress={handleClaimDailyBonus}
                disabled={claiming}
              >
                {claiming ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text style={styles.claimButtonText}>Klaim Bonus Harian</Text>
                )}
              </TouchableOpacity>
            )}
          </>
        )}
      </View>

      {/* Mining Packages Section */}
      {user?.role === 'member' ? (
        <>
          {/* Mining Stats */}
          {stats && (
            <View style={styles.card}>
              <Text style={styles.cardTitle}>Statistik Mining</Text>
              <View style={styles.statsRow}>
                <View style={styles.statItem}>
                  <Text style={styles.statValue}>{formatGC(stats.total_mined)}</Text>
                  <Text style={styles.statLabel}>Total Mined</Text>
                </View>
                <View style={styles.statItem}>
                  <Text style={styles.statValue}>{stats.active_packages?.length || 0}</Text>
                  <Text style={styles.statLabel}>Paket Aktif</Text>
                </View>
              </View>
            </View>
          )}

          {/* Active Packages */}
          {activePackages.length > 0 && (
            <View style={styles.card}>
              <Text style={styles.cardTitle}>Paket Aktif</Text>
              {activePackages.map((pkg) => {
                const daysElapsed = pkg.days_elapsed || 0;
                const totalDays = 60;
                const progress = Math.min(daysElapsed / totalDays, 1);
                return (
                  <View key={pkg.id} style={styles.packageItem}>
                    <Text style={styles.packageName}>{pkg.plan_name || pkg.name}</Text>
                    <View style={styles.progressContainer}>
                      <View style={[styles.progressBar, { width: `${progress * 100}%` }]} />
                    </View>
                    <Text style={styles.progressText}>
                      {daysElapsed} / {totalDays} hari
                    </Text>

                    <TouchableOpacity
                      style={styles.claimMiningButton}
                      onPress={() => handleClaimMining(pkg.plan_id || pkg.id)}
                      disabled={claimingPlan === (pkg.plan_id || pkg.id)}
                    >
                      {claimingPlan === (pkg.plan_id || pkg.id) ? (
                        <ActivityIndicator color="#fff" size="small" />
                      ) : (
                        <Text style={styles.claimMiningButtonText}>Klaim Harian</Text>
                      )}
                    </TouchableOpacity>
                  </View>
                );
              })}
            </View>
          )}

          {/* Available Plans */}
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Paket Mining Tersedia</Text>
            {plans.map((plan) => (
              <View key={plan.id} style={styles.planItem}>
                <Text style={styles.planName}>{plan.name}</Text>
                <View style={styles.planDetails}>
                  <Text style={styles.planDetail}>
                    Deposit: {formatGC(plan.deposit)}
                  </Text>

                  <Text style={styles.planDetail}>Durasi: 60 hari</Text>
                </View>
                <TouchableOpacity
                  style={styles.activateButton}
                  onPress={() => handleActivatePlan(plan)}
                  disabled={activatingPlan === plan.id}
                >
                  {activatingPlan === plan.id ? (
                    <ActivityIndicator color="#fff" size="small" />
                  ) : (
                    <Text style={styles.activateButtonText}>Aktifkan</Text>
                  )}
                </TouchableOpacity>
              </View>
            ))}
          </View>
        </>
      ) : (
        <View style={styles.card}>
          <Ionicons name="lock-closed-outline" size={48} color="#9ca3af" style={styles.lockIcon} />
          <Text style={styles.upgradeText}>
            Upgrade ke Member untuk menggunakan Mining
          </Text>
          <TouchableOpacity
            style={styles.upgradeButton}
            onPress={() => navigation.navigate('ProfileTab', { screen: 'Membership' })}
          >
            <Text style={styles.upgradeButtonText}>Upgrade Sekarang</Text>
          </TouchableOpacity>
        </View>
      )}

      <View style={{ height: 30 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f3f4f6' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  card: {
    backgroundColor: '#fff',
    margin: 16,
    marginBottom: 0,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  cardHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
  cardTitle: { fontSize: 18, fontWeight: '700', color: '#1f2937', marginLeft: 8 },
  bonusAmount: {
    fontSize: 28,
    fontWeight: '800',
    color: '#f59e0b',
    textAlign: 'center',
    marginVertical: 8,
  },
  claimedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 8,
  },
  claimedText: { color: '#10b981', fontWeight: '600', marginLeft: 6 },
  claimButton: {
    backgroundColor: '#f59e0b',
    borderRadius: 8,
    paddingVertical: 12,
    alignItems: 'center',
    marginTop: 12,
  },
  claimButtonText: { color: '#fff', fontWeight: '700', fontSize: 16 },
  statsRow: { flexDirection: 'row', justifyContent: 'space-around', marginTop: 12 },
  statItem: { alignItems: 'center' },
  statValue: { fontSize: 20, fontWeight: '700', color: '#f59e0b' },
  statLabel: { fontSize: 13, color: '#6b7280', marginTop: 4 },
  packageItem: {
    backgroundColor: '#fffbeb',
    borderRadius: 8,
    padding: 12,
    marginTop: 12,
    borderWidth: 1,
    borderColor: '#fde68a',
  },
  packageName: { fontSize: 16, fontWeight: '700', color: '#1f2937' },
  progressContainer: {
    height: 8,
    backgroundColor: '#e5e7eb',
    borderRadius: 4,
    marginTop: 8,
    overflow: 'hidden',
  },
  progressBar: { height: '100%', backgroundColor: '#f59e0b', borderRadius: 4 },
  progressText: { fontSize: 12, color: '#6b7280', marginTop: 4 },
  dailyClaim: { fontSize: 14, color: '#374151', marginTop: 4 },
  claimMiningButton: {
    backgroundColor: '#10b981',
    borderRadius: 6,
    paddingVertical: 8,
    alignItems: 'center',
    marginTop: 8,
  },
  claimMiningButtonText: { color: '#fff', fontWeight: '600' },
  planItem: {
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    padding: 12,
    marginTop: 12,
  },
  planName: { fontSize: 16, fontWeight: '700', color: '#1f2937' },
  planDetails: { marginTop: 8 },
  planDetail: { fontSize: 14, color: '#4b5563', marginTop: 2 },
  activateButton: {
    backgroundColor: '#f59e0b',
    borderRadius: 6,
    paddingVertical: 10,
    alignItems: 'center',
    marginTop: 12,
  },
  activateButtonText: { color: '#fff', fontWeight: '700' },
  lockIcon: { alignSelf: 'center', marginBottom: 12 },
  upgradeText: {
    fontSize: 16,
    color: '#6b7280',
    textAlign: 'center',
    marginBottom: 16,
  },
  upgradeButton: {
    backgroundColor: '#f59e0b',
    borderRadius: 8,
    paddingVertical: 12,
    alignItems: 'center',
  },
  upgradeButtonText: { color: '#fff', fontWeight: '700', fontSize: 16 },
});
