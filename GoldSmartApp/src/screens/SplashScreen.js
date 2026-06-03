import React, { useEffect } from 'react';
import { View, Text, ActivityIndicator, StyleSheet } from 'react-native';
import { useAuthStore } from '../store/authStore';
import { useConfigStore } from '../store/configStore';

export default function SplashScreen({ navigation }) {
  const loadToken = useAuthStore((s) => s.loadToken);
  const isLoggedIn = useAuthStore((s) => s.isLoggedIn);
  const fetchConfig = useConfigStore((s) => s.fetchConfig);

  useEffect(() => {
    const init = async () => {
      await Promise.all([loadToken(), fetchConfig()]);
    };
    init();
  }, []);

  useEffect(() => {
    if (isLoggedIn === true) {
      navigation.replace('Main');
    } else if (isLoggedIn === false) {
      navigation.replace('Auth');
    }
  }, [isLoggedIn]);

  return (
    <View style={styles.container}>
      <Text style={styles.title}>GoldSmart</Text>
      <ActivityIndicator size="large" color="#DAA520" style={styles.loader} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#1a1a2e',
  },
  title: {
    fontSize: 36,
    fontWeight: 'bold',
    color: '#DAA520',
    marginBottom: 24,
  },
  loader: {
    marginTop: 16,
  },
});
