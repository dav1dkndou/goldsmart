import React, { useState, useCallback } from 'react';
import {
  View, Text, TouchableOpacity, StyleSheet,
  ActivityIndicator, Alert, ScrollView, TextInput,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import * as membership from '../../api/membership';
import useAuthStore from '../../store/authStore';

const BENEFITS = [
  { icon: 'hardware-chip-outline', text: 'Akses fitur Mining GC' },
  { icon: 'cash-outline', text: 'Withdrawal saldo GC' },
  { icon: 'videocam-outline', text: 'Upload video edukasi' },
  { icon: 'gift-outline', text: 'Daily bonus lebih tinggi' },
  { icon: 'star-outline', text: 'Prioritas support' },
];

export default function MembershipScreen() {
  const fetchUser = useAuthStore((s) => s.fetchUser);
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [reason, setReason] = useState('');

  const fetchStatus = async () => {
    try {
      const res = await membership.getMembershipStatus();
      setStatus(res.data.data || res.data);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat status membership');
    } finally {
      setLoading(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchStatus();
    }, [])
  );

  const handleRequest = async (type) => {
    const label = type === 'upgrade' ? 'Upgrade ke Member' : 'Downgrade ke User';
    Alert.alert(label, `Yakin ingin ${label.toLowerCase()}?`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Ya',
        onPress: async () => {
          setActionLoading(true);
          try {
            await membership.requestMembership(type, type === 'upgrade' ? reason : undefined);
            Alert.alert('Berhasil', 'Request telah dikirim, menunggu persetujuan admin');
            await fetchStatus();
            await fetchUser();
          } catch (e) {
            const msg = e.response?.data?.message || 'Gagal mengirim request';
            Alert.alert('Error', msg);
          } finally {
            setActionLoading(false);
          }
        },
      },
    ]);
  };

  const handleCancel = () => {
    Alert.alert('Batalkan Request', 'Yakin ingin membatalkan request membership?', [
      { text: 'Tidak', style: 'cancel' },
      {
        text: 'Ya, Batalkan',
        style: 'destructive',
        onPress: async () => {
          setActionLoading(true);
          try {
            await membership.cancelMembershipRequest();
            Alert.alert('Berhasil', 'Request dibatalkan');
            await fetchStatus();
          } catch (e) {
            const msg = e.response?.data?.message || 'Gagal membatalkan request';
            Alert.alert('Error', msg);
          } finally {
            setActionLoading(false);
          }
        },
      },
    ]);
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#DAA520" />
      </View>
    );
  }

  const role = status?.current_role || status?.role || status?.user?.role || 'user';
  const isMember = role === 'member';
  const hasPending = status?.pending_request || status?.has_pending;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.statusCard}>
        <Ionicons
          name={isMember ? 'shield-checkmark' : 'shield-outline'}
          size={48}
          color="#DAA520"
        />
        <Text style={styles.statusTitle}>
          {isMember ? 'Member Aktif' : 'User Biasa'}
        </Text>
        <View style={[styles.badge, isMember && styles.memberBadge]}>
          <Text style={styles.badgeText}>{isMember ? 'MEMBER' : 'USER'}</Text>
        </View>
      </View>

      {hasPending && (
        <View style={styles.pendingCard}>
          <Ionicons name="time-outline" size={24} color="#F59E0B" />
          <Text style={styles.pendingText}>Menunggu Persetujuan Admin</Text>
          <TouchableOpacity
            style={styles.cancelBtn}
            onPress={handleCancel}
            disabled={actionLoading}
          >
            {actionLoading ? (
              <ActivityIndicator size="small" color="#EF4444" />
            ) : (
              <Text style={styles.cancelBtnText}>Batalkan Request</Text>
            )}
          </TouchableOpacity>
        </View>
      )}

      {!isMember && !hasPending && (
        <>
          <Text style={styles.sectionTitle}>Keuntungan Member</Text>
          {BENEFITS.map((item, idx) => (
            <View key={idx} style={styles.benefitRow}>
              <Ionicons name={item.icon} size={20} color="#DAA520" />
              <Text style={styles.benefitText}>{item.text}</Text>
            </View>
          ))}
          
          <Text style={[styles.sectionTitle, { marginTop: 16 }]}>Catatan untuk Admin</Text>
          <TextInput
            style={styles.reasonInput}
            placeholder="Tulis alasan Anda (opsional)..."
            placeholderTextColor="#999"
            value={reason}
            onChangeText={setReason}
            multiline
          />

          <TouchableOpacity
            style={[styles.actionBtn, actionLoading && { opacity: 0.6 }]}
            onPress={() => handleRequest('upgrade')}
            disabled={actionLoading}
          >
            {actionLoading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.actionBtnText}>Upgrade ke Member</Text>
            )}
          </TouchableOpacity>
        </>
      )}

      {isMember && !hasPending && (
        <>
          <Text style={styles.sectionTitle}>Keuntungan Anda sebagai Member</Text>
          <View style={styles.memberBenefitsContainer}>
            {BENEFITS.map((item, idx) => (
              <View key={idx} style={styles.memberBenefitRow}>
                <Ionicons name="checkmark-circle" size={24} color="#10B981" />
                <Text style={styles.memberBenefitText}>{item.text}</Text>
              </View>
            ))}
          </View>
        </>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f5f5f5' },
  statusCard: {
    backgroundColor: '#fff', borderRadius: 12,
    padding: 24, alignItems: 'center', marginBottom: 16,
    elevation: 2, shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.1, shadowRadius: 2,
  },
  statusTitle: { color: '#1a1a2e', fontSize: 20, fontWeight: 'bold', marginTop: 12 },
  badge: {
    backgroundColor: '#999', borderRadius: 12,
    paddingHorizontal: 16, paddingVertical: 4, marginTop: 8,
  },
  memberBadge: { backgroundColor: '#DAA520' },
  badgeText: { color: '#fff', fontSize: 12, fontWeight: 'bold' },
  pendingCard: {
    backgroundColor: '#fffbeb', borderRadius: 12,
    padding: 16, alignItems: 'center', marginBottom: 16,
    borderWidth: 1, borderColor: '#fde68a'
  },
  pendingText: { color: '#F59E0B', fontSize: 15, fontWeight: '600', marginTop: 8 },
  cancelBtn: {
    marginTop: 12, borderWidth: 1, borderColor: '#EF4444',
    borderRadius: 8, paddingHorizontal: 20, paddingVertical: 8,
  },
  cancelBtnText: { color: '#EF4444', fontSize: 14, fontWeight: '600' },
  sectionTitle: { color: '#1a1a2e', fontSize: 16, fontWeight: 'bold', marginBottom: 12 },
  benefitRow: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: '#fff', borderRadius: 10,
    padding: 12, marginBottom: 8, gap: 12,
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.05, shadowRadius: 1,
  },
  benefitText: { color: '#666', fontSize: 14, flex: 1 },
  actionBtn: {
    backgroundColor: '#DAA520', borderRadius: 8,
    paddingVertical: 14, alignItems: 'center', marginTop: 16,
  },
  actionBtnText: { color: '#fff', fontSize: 16, fontWeight: 'bold' },
  reasonInput: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 12,
    color: '#1a1a2e',
    fontSize: 14,
    minHeight: 80,
    textAlignVertical: 'top',
    borderWidth: 1,
    borderColor: '#eee'
  },
  memberBenefitsContainer: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    elevation: 2, shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.1, shadowRadius: 2,
  },
  memberBenefitRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    gap: 12,
  },
  memberBenefitText: {
    color: '#1a1a2e',
    fontSize: 15,
    flex: 1,
  },
});
