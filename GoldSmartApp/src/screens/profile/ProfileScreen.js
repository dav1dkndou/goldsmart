import React, { useState, useCallback } from 'react';
import {
  View, Text, Image, TouchableOpacity, StyleSheet,
  ActivityIndicator, Alert, ScrollView,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, CommonActions } from '@react-navigation/native';
import * as users from '../../api/users';
import useAuthStore from '../../store/authStore';
import { formatGC, getFullUrl } from '../../utils/helpers';

export default function ProfileScreen({ navigation }) {
  const logout = useAuthStore((s) => s.logout);
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchProfile = async () => {
    try {
      const res = await users.getProfile();
      setProfile(res.data.data || res.data);
    } catch (e) {
      Alert.alert('Error', 'Gagal memuat profil');
    } finally {
      setLoading(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchProfile();
    }, [])
  );

  const handleLogout = () => {
    Alert.alert('Logout', 'Yakin ingin keluar?', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Logout',
        style: 'destructive',
        onPress: async () => {
          await logout();
          navigation.dispatch(
            CommonActions.reset({ index: 0, routes: [{ name: 'Login' }] })
          );
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

  const menuItems = [
    { label: 'Edit Profile', icon: 'person-outline', screen: 'EditProfile' },
    { label: 'Riwayat Transaksi', icon: 'receipt-outline', screen: 'TransactionList' },
    { label: 'Ubah Password', icon: 'lock-closed-outline', screen: 'ChangePassword' },
    { label: 'Membership', icon: 'shield-outline', screen: 'Membership' },
    { label: 'Referral', icon: 'people-outline', screen: 'Referral' },
    { label: 'GC Wallet / Riwayat Withdrawal', icon: 'wallet-outline', screen: 'WithdrawalHistory' },
  ];

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        {profile?.avatar ? (
          <Image
            source={{ uri: getFullUrl(`uploads/avatars/${profile.avatar}`) }}
            style={styles.avatar}
          />
        ) : (
          <View style={[styles.avatar, styles.avatarPlaceholder]}>
            <Ionicons name="person" size={40} color="#999" />
          </View>
        )}
        <Text style={styles.name}>{profile?.name}</Text>
        <Text style={styles.email}>{profile?.email}</Text>
        {profile?.phone && <Text style={styles.phone}>{profile.phone}</Text>}
        <View style={[styles.roleBadge, profile?.role === 'member' && styles.memberBadge]}>
          <Text style={styles.roleText}>
            {profile?.role === 'member' ? 'Member' : 'User'}
          </Text>
        </View>
      </View>

      <View style={styles.balanceCard}>
        <Text style={styles.balanceLabel}>GC Balance</Text>
        <Text style={styles.balanceValue}>{formatGC(profile?.gc_balance)}</Text>
      </View>

      <View style={styles.menuSection}>
        {menuItems.map((item, idx) => (
          <TouchableOpacity
            key={idx}
            style={styles.menuItem}
            onPress={() => navigation.navigate(item.screen)}
          >
            <Ionicons name={item.icon} size={22} color="#DAA520" />
            <Text style={styles.menuLabel}>{item.label}</Text>
            <Ionicons name="chevron-forward" size={18} color="#666" />
          </TouchableOpacity>
        ))}
      </View>

      <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
        <Ionicons name="log-out-outline" size={20} color="#EF4444" />
        <Text style={styles.logoutText}>Logout</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f5f5f5' },
  header: { alignItems: 'center', paddingTop: 24, paddingBottom: 16 },
  avatar: { width: 80, height: 80, borderRadius: 40, backgroundColor: '#eee' },
  avatarPlaceholder: { justifyContent: 'center', alignItems: 'center' },
  name: { color: '#1a1a2e', fontSize: 20, fontWeight: 'bold', marginTop: 12 },
  email: { color: '#666', fontSize: 14, marginTop: 2 },
  phone: { color: '#666', fontSize: 13, marginTop: 2 },
  roleBadge: {
    backgroundColor: '#999', borderRadius: 12,
    paddingHorizontal: 14, paddingVertical: 4, marginTop: 8,
  },
  memberBadge: { backgroundColor: '#DAA520' },
  roleText: { color: '#fff', fontSize: 12, fontWeight: '600' },
  balanceCard: {
    backgroundColor: '#fff', marginHorizontal: 16, borderRadius: 12,
    padding: 16, alignItems: 'center', marginBottom: 16,
    elevation: 2, shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.1, shadowRadius: 2,
  },
  balanceLabel: { color: '#666', fontSize: 13 },
  balanceValue: { color: '#DAA520', fontSize: 24, fontWeight: 'bold', marginTop: 4 },
  menuSection: { marginHorizontal: 16 },
  menuItem: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: '#fff', borderRadius: 10,
    padding: 14, marginBottom: 8,
    elevation: 1, shadowColor: '#000', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.05, shadowRadius: 1,
  },
  menuLabel: { color: '#1a1a2e', fontSize: 14, flex: 1, marginLeft: 12 },
  logoutBtn: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center',
    marginHorizontal: 16, marginTop: 16, marginBottom: 32,
    padding: 14, borderRadius: 10, borderWidth: 1, borderColor: '#EF4444',
    backgroundColor: '#fff'
  },
  logoutText: { color: '#EF4444', fontSize: 15, fontWeight: '600', marginLeft: 8 },
});
