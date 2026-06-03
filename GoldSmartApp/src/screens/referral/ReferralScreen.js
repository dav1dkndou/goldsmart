import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  StyleSheet,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import * as Sharing from 'expo-sharing';
import * as users from '../../api/users';
import { useAuthStore } from '../../store/authStore';
import { formatGC, formatDate } from '../../utils/helpers';

export default function ReferralScreen() {
  const { user } = useAuthStore();
  const [referralData, setReferralData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchData = useCallback(async () => {
    try {
      const res = await users.getReferrals();
      setReferralData(res.data.data || res.data);
    } catch (err) {
      Alert.alert('Error', err.response?.data?.message || 'Gagal memuat data referral');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  const handleCopyCode = async () => {
    const code = referralData?.referral_code || user?.referral_code || '';
    await Clipboard.setStringAsync(code);
    Alert.alert('Tersalin!', 'Kode referral berhasil disalin');
  };

  const handleShare = async () => {
    const code = referralData?.referral_code || user?.referral_code || '';
    const message = `Bergabung dengan GoldSmart dan dapatkan bonus! Gunakan kode referral saya: ${code}`;
    try {
      const isAvailable = await Sharing.isAvailableAsync();
      if (isAvailable) {
        await Sharing.shareAsync(message);
      } else {
        await Clipboard.setStringAsync(message);
        Alert.alert('Info', 'Sharing tidak tersedia. Teks telah disalin ke clipboard.');
      }
    } catch {
      await Clipboard.setStringAsync(message);
      Alert.alert('Info', 'Teks referral telah disalin ke clipboard.');
    }
  };

  const referralCode = referralData?.referral_code || user?.referral_code || '-';
  const totalReferrals = referralData?.total_referrals || 0;
  const totalBonus = referralData?.total_bonus || 0;
  const referredUsers = referralData?.referrals || referralData?.referred_users || [];

  const renderHeader = () => (
    <View>
      {/* Referral Code Card */}
      <View style={styles.codeCard}>
        <Text style={styles.codeLabel}>Kode Referral Anda</Text>
        <Text style={styles.codeText}>{referralCode}</Text>
        <View style={styles.codeActions}>
          <TouchableOpacity style={styles.copyButton} onPress={handleCopyCode}>
            <Ionicons name="copy-outline" size={18} color="#fff" />
            <Text style={styles.copyButtonText}>Salin Kode</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.shareButton} onPress={handleShare}>
            <Ionicons name="share-social-outline" size={18} color="#f59e0b" />
            <Text style={styles.shareButtonText}>Bagikan</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Stats */}
      <View style={styles.statsCard}>
        <View style={styles.statItem}>
          <Ionicons name="people-outline" size={24} color="#f59e0b" />
          <Text style={styles.statValue}>{totalReferrals}</Text>
          <Text style={styles.statLabel}>Total Referral</Text>
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <Ionicons name="diamond-outline" size={24} color="#f59e0b" />
          <Text style={styles.statValue}>{formatGC(totalBonus)} GC</Text>
          <Text style={styles.statLabel}>Total Bonus</Text>
        </View>
      </View>

      {/* Explanation Card */}
      <View style={styles.infoCard}>
        <Ionicons name="information-circle-outline" size={22} color="#3b82f6" />
        <View style={styles.infoContent}>
          <Text style={styles.infoTitle}>Cara Kerja Referral</Text>
          <Text style={styles.infoText}>
            1. Bagikan kode referral Anda ke teman{'\n'}
            2. Teman mendaftar menggunakan kode Anda{'\n'}
            3. Anda mendapat bonus GC untuk setiap referral{'\n'}
            4. Bonus tambahan saat referral upgrade ke Member
          </Text>
        </View>
      </View>

      {/* Referred Users Header */}
      {referredUsers.length > 0 && (
        <Text style={styles.sectionTitle}>Daftar Referral</Text>
      )}
    </View>
  );

  const renderItem = useCallback(({ item }) => (
    <View style={styles.userItem}>
      <View style={styles.userAvatar}>
        <Text style={styles.avatarText}>
          {(item.name || '?').charAt(0).toUpperCase()}
        </Text>
      </View>
      <View style={styles.userInfo}>
        <Text style={styles.userName}>{item.name}</Text>
        <Text style={styles.userDate}>Bergabung {formatDate(item.joined_at || item.created_at)}</Text>
      </View>
      <View style={styles.userRight}>
        <View style={[
          styles.roleBadge,
          { backgroundColor: item.role === 'member' ? '#fef3c7' : '#f3f4f6' },
        ]}>
          <Text style={[
            styles.roleText,
            { color: item.role === 'member' ? '#d97706' : '#6b7280' },
          ]}>
            {item.role === 'member' ? 'Member' : 'User'}
          </Text>
        </View>
        <Text style={styles.userBonus}>+{formatGC(item.bonus_earned || 0)} GC</Text>
      </View>
    </View>
  ), []);

  const keyExtractor = useCallback((item, index) => item.id?.toString() || index.toString(), []);

  const renderEmpty = () => (
    <View style={styles.empty}>
      <Ionicons name="people-outline" size={48} color="#d1d5db" />
      <Text style={styles.emptyText}>Belum ada referral</Text>
    </View>
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#f59e0b" />
      </View>
    );
  }

  return (
    <FlatList
      style={styles.container}
      data={referredUsers}
      keyExtractor={keyExtractor}
      renderItem={renderItem}
      ListHeaderComponent={renderHeader}
      ListEmptyComponent={renderEmpty}
      refreshing={refreshing}
      onRefresh={onRefresh}
      contentContainerStyle={referredUsers.length === 0 ? undefined : { paddingBottom: 30 }}
    />
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f3f4f6' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  codeCard: {
    backgroundColor: '#fff',
    margin: 16,
    marginBottom: 0,
    borderRadius: 12,
    padding: 20,
    alignItems: 'center',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  codeLabel: { fontSize: 14, color: '#6b7280', marginBottom: 8 },
  codeText: {
    fontSize: 32,
    fontWeight: '800',
    color: '#f59e0b',
    letterSpacing: 3,
    marginBottom: 16,
  },
  codeActions: { flexDirection: 'row', gap: 12 },
  copyButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f59e0b',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
    gap: 6,
  },
  copyButtonText: { color: '#fff', fontWeight: '600' },
  shareButton: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1.5,
    borderColor: '#f59e0b',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
    gap: 6,
  },
  shareButtonText: { color: '#f59e0b', fontWeight: '600' },
  statsCard: {
    flexDirection: 'row',
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
  statItem: { flex: 1, alignItems: 'center' },
  statValue: { fontSize: 20, fontWeight: '700', color: '#1f2937', marginTop: 6 },
  statLabel: { fontSize: 12, color: '#9ca3af', marginTop: 4 },
  statDivider: { width: 1, backgroundColor: '#e5e7eb' },
  infoCard: {
    flexDirection: 'row',
    backgroundColor: '#eff6ff',
    margin: 16,
    marginBottom: 0,
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  infoContent: { flex: 1, marginLeft: 10 },
  infoTitle: { fontSize: 14, fontWeight: '700', color: '#1e40af', marginBottom: 6 },
  infoText: { fontSize: 13, color: '#1e40af', lineHeight: 20 },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1f2937',
    marginHorizontal: 16,
    marginTop: 20,
    marginBottom: 4,
  },
  userItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    marginHorizontal: 16,
    marginTop: 10,
    borderRadius: 10,
    padding: 12,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 1,
  },
  userAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#fef3c7',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: { fontSize: 16, fontWeight: '700', color: '#d97706' },
  userInfo: { flex: 1, marginLeft: 10 },
  userName: { fontSize: 15, fontWeight: '600', color: '#1f2937' },
  userDate: { fontSize: 12, color: '#9ca3af', marginTop: 2 },
  userRight: { alignItems: 'flex-end' },
  roleBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  roleText: { fontSize: 11, fontWeight: '600' },
  userBonus: { fontSize: 13, fontWeight: '600', color: '#10b981', marginTop: 4 },
  empty: { alignItems: 'center', paddingVertical: 40 },
  emptyText: { fontSize: 15, color: '#9ca3af', marginTop: 12 },
});
