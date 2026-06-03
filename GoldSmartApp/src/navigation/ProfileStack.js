import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import ProfileScreen from '../screens/profile/ProfileScreen';
import EditProfileScreen from '../screens/profile/EditProfileScreen';
import ChangePasswordScreen from '../screens/profile/ChangePasswordScreen';
import MembershipScreen from '../screens/profile/MembershipScreen';
import ReferralScreen from '../screens/referral/ReferralScreen';
import WithdrawalFormScreen from '../screens/withdrawal/WithdrawalFormScreen';
import WithdrawalHistoryScreen from '../screens/withdrawal/WithdrawalHistoryScreen';
import WithdrawalDetailScreen from '../screens/withdrawal/WithdrawalDetailScreen';
import TransactionListScreen from '../screens/transactions/TransactionListScreen';
import TransactionDetailScreen from '../screens/transactions/TransactionDetailScreen';

const Stack = createNativeStackNavigator();

export default function ProfileStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: '#D4AF37' },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: 'bold' },
      }}
    >
      <Stack.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profil' }} />
      <Stack.Screen name="EditProfile" component={EditProfileScreen} options={{ title: 'Edit Profil' }} />
      <Stack.Screen name="ChangePassword" component={ChangePasswordScreen} options={{ title: 'Ubah Password' }} />
      <Stack.Screen name="Membership" component={MembershipScreen} options={{ title: 'Membership' }} />
      <Stack.Screen name="Referral" component={ReferralScreen} options={{ title: 'Referral' }} />
      <Stack.Screen name="WithdrawalForm" component={WithdrawalFormScreen} options={{ title: 'Withdrawal' }} />
      <Stack.Screen name="WithdrawalHistory" component={WithdrawalHistoryScreen} options={{ title: 'Riwayat Withdrawal' }} />
      <Stack.Screen name="WithdrawalDetail" component={WithdrawalDetailScreen} options={{ title: 'Detail Withdrawal' }} />
      <Stack.Screen name="TransactionList" component={TransactionListScreen} options={{ title: 'Riwayat Pesanan' }} />
      <Stack.Screen name="TransactionDetail" component={TransactionDetailScreen} options={{ title: 'Detail Pesanan' }} />
    </Stack.Navigator>
  );
}
