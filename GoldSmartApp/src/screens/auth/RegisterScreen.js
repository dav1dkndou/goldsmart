import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
} from 'react-native';
import { useAuthStore } from '../../store/authStore';

export default function RegisterScreen({ navigation }) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [referralCode, setReferralCode] = useState('');
  const register = useAuthStore((s) => s.register);
  const loading = useAuthStore((s) => s.isLoading);

  const handleRegister = async () => {
    if (!name.trim() || !email.trim() || !phone.trim() || !password || !passwordConfirm) {
      Alert.alert('Error', 'Semua field wajib harus diisi.');
      return;
    }
    if (password !== passwordConfirm) {
      Alert.alert('Error', 'Password dan konfirmasi password tidak cocok.');
      return;
    }
    try {
      await register(name.trim(), email.trim(), phone.trim(), password, passwordConfirm, referralCode.trim() || undefined);
      navigation.reset({ index: 0, routes: [{ name: 'Main' }] });
    } catch (e) {
      const msg =
        e.response?.data?.message || e.message || 'Registrasi gagal. Coba lagi.';
      Alert.alert('Registrasi Gagal', msg);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={styles.container}
        keyboardShouldPersistTaps="handled"
      >
        <Text style={styles.title}>GoldSmart</Text>
        <Text style={styles.subtitle}>Buat akun baru</Text>

        <TextInput
          style={styles.input}
          placeholder="Nama"
          placeholderTextColor="#999"
          value={name}
          onChangeText={setName}
        />
        <TextInput
          style={styles.input}
          placeholder="Email"
          placeholderTextColor="#999"
          keyboardType="email-address"
          autoCapitalize="none"
          value={email}
          onChangeText={setEmail}
        />
        <TextInput
          style={styles.input}
          placeholder="No. Telepon"
          placeholderTextColor="#999"
          keyboardType="phone-pad"
          value={phone}
          onChangeText={setPhone}
        />
        <TextInput
          style={styles.input}
          placeholder="Password"
          placeholderTextColor="#999"
          secureTextEntry
          value={password}
          onChangeText={setPassword}
        />
        <TextInput
          style={styles.input}
          placeholder="Konfirmasi Password"
          placeholderTextColor="#999"
          secureTextEntry
          value={passwordConfirm}
          onChangeText={setPasswordConfirm}
        />
        <TextInput
          style={styles.input}
          placeholder="Kode Referral (opsional)"
          placeholderTextColor="#999"
          autoCapitalize="none"
          value={referralCode}
          onChangeText={setReferralCode}
        />

        <TouchableOpacity
          style={styles.button}
          onPress={handleRegister}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.buttonText}>Daftar</Text>
          )}
        </TouchableOpacity>

        <TouchableOpacity onPress={() => navigation.navigate('Login')}>
          <Text style={styles.link}>
            Sudah punya akun? <Text style={styles.linkBold}>Login</Text>
          </Text>
        </TouchableOpacity>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: '#1a1a2e' },
  container: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 24,
  },
  title: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#DAA520',
    textAlign: 'center',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 14,
    color: '#ccc',
    textAlign: 'center',
    marginBottom: 32,
  },
  input: {
    backgroundColor: '#2a2a4a',
    color: '#fff',
    borderRadius: 8,
    padding: 14,
    fontSize: 16,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: '#3a3a5a',
  },
  button: {
    backgroundColor: '#DAA520',
    borderRadius: 8,
    padding: 16,
    alignItems: 'center',
    marginTop: 8,
    marginBottom: 20,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  link: {
    color: '#ccc',
    textAlign: 'center',
    fontSize: 14,
  },
  linkBold: {
    color: '#DAA520',
    fontWeight: 'bold',
  },
});
