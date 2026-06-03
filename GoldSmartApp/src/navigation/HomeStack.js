import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import HomeScreen from '../screens/home/HomeScreen';
import ProductDetailScreen from '../screens/products/ProductDetailScreen';
import CartScreen from '../screens/cart/CartScreen';
import CheckoutScreen from '../screens/cart/CheckoutScreen';
import TransactionListScreen from '../screens/transactions/TransactionListScreen';
import TransactionDetailScreen from '../screens/transactions/TransactionDetailScreen';

const Stack = createNativeStackNavigator();

export default function HomeStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: '#D4AF37' },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: 'bold' },
      }}
    >
      <Stack.Screen name="Home" component={HomeScreen} options={{ headerShown: false }} />
      <Stack.Screen name="ProductDetail" component={ProductDetailScreen} options={{ title: 'Detail Produk' }} />
      <Stack.Screen name="Cart" component={CartScreen} options={{ title: 'Keranjang' }} />
      <Stack.Screen name="Checkout" component={CheckoutScreen} options={{ title: 'Checkout' }} />
      <Stack.Screen name="TransactionList" component={TransactionListScreen} options={{ title: 'Riwayat Pesanan' }} />
      <Stack.Screen name="TransactionDetail" component={TransactionDetailScreen} options={{ title: 'Detail Pesanan' }} />
    </Stack.Navigator>
  );
}
