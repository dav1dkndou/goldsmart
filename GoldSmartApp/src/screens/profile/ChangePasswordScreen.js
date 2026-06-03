import React, { useState } from 'react';
import {
  View, Text, TextInput, TouchableOpacity,
  StyleSheet, Alert, ActivityIndicator, ScrollView,
} from 'react-native';
import * as users from '../../api/users';

export default function ChangePasswordScreen({ navigation }) {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!currentPassword || !newPassword || !confirmPassword) {
      return Alert.alert('Error', 'Semua field harus diisi');
    }
    if (newPassword !== confirmPassword) {
      return Alert.alert('Error', 'Password baru dan konfirmasi tidak sama');
    }
    if (newPassword.length < 6) {
      return Alert.alert('Error', 'Password baru minimal 6 karakter');
    }

    setLoading(true);
    try {
      await users.changePassword(currentPassword, newPassword, confirmPassword);
      Alert.alert('Berhasil', 'Password berhasil diubah', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (e) {
      const msg = e.response?.data?.message || 'Gagal mengubah password';
      Alert.alert('Error', msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.label}>Password Lama</Text>
      <TextInput
        style={styles.input}
        value={currentPassword}
        onChangeText={setCurrentPassword}
        placeholder="Masukkan password lama"
        placeholderTextColor="#999"
        secureTextEntry
      />

      <Text style={styles.label}>Password Baru</Text>
      <TextInput
        style={styles.input}
        value={newPassword}
        onChangeText={setNewPassword}
        placeholder="Masukkan password baru"
        placeholderTextColor="#999"
        secureTextEntry
      />

      <Text style={styles.label}>Konfirmasi Password Baru</Text>
      <TextInput
        style={styles.input}
        value={confirmPassword}
        onChangeText={setConfirmPassword}
        placeholder="Ulangi password baru"
        placeholderTextColor="#999"
        secureTextEntry
      />

      <TouchableOpacity
        style={[styles.submitBtn, loading && { opacity: 0.6 }]}
        onPress={handleSubmit}
        disabled={loading}
      >
        {loading ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.submitBtnText}>Ubah Password</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  content: { padding: 16 },
  label: { color: '#DAA520', fontSize: 14, fontWeight: '600', marginTop: 16, marginBottom: 6 },
  input: {
    backgroundColor: '#fff', borderRadius: 8,
    paddingHorizontal: 14, paddingVertical: 10, color: '#1a1a2e', fontSize: 14,
    borderWidth: 1, borderColor: '#eee',
  },
  submitBtn: {
    backgroundColor: '#DAA520', borderRadius: 8,
    paddingVertical: 14, alignItems: 'center', marginTop: 24,
  },
  submitBtnText: { color: '#fff', fontSize: 16, fontWeight: 'bold' },
});
