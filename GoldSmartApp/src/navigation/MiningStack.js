import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import MiningScreen from '../screens/mining/MiningScreen';
import MiningHistoryScreen from '../screens/mining/MiningHistoryScreen';
import WithdrawalFormScreen from '../screens/withdrawal/WithdrawalFormScreen';
import WithdrawalHistoryScreen from '../screens/withdrawal/WithdrawalHistoryScreen';
import WithdrawalDetailScreen from '../screens/withdrawal/WithdrawalDetailScreen';

const Stack = createNativeStackNavigator();

export default function MiningStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: '#D4AF37' },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: 'bold' },
      }}
    >
      <Stack.Screen name="Mining" component={MiningScreen} options={{ title: 'Mining' }} />
      <Stack.Screen name="MiningHistory" component={MiningHistoryScreen} options={{ title: 'Riwayat Mining' }} />
      <Stack.Screen name="WithdrawalForm" component={WithdrawalFormScreen} options={{ title: 'Withdrawal' }} />
      <Stack.Screen name="WithdrawalHistory" component={WithdrawalHistoryScreen} options={{ title: 'Riwayat Withdrawal' }} />
      <Stack.Screen name="WithdrawalDetail" component={WithdrawalDetailScreen} options={{ title: 'Detail Withdrawal' }} />
    </Stack.Navigator>
  );
}
