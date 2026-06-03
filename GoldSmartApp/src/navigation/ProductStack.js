import React from 'react';
import { TouchableOpacity, View, Text } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { Ionicons } from '@expo/vector-icons';
import useCartStore from '../store/cartStore';

import ProductListScreen from '../screens/products/ProductListScreen';
import ProductDetailScreen from '../screens/products/ProductDetailScreen';
import CartScreen from '../screens/cart/CartScreen';
import CheckoutScreen from '../screens/cart/CheckoutScreen';
import TransactionListScreen from '../screens/transactions/TransactionListScreen';
import TransactionDetailScreen from '../screens/transactions/TransactionDetailScreen';

const Stack = createNativeStackNavigator();

const CartButton = ({ navigation }) => {
  const items = useCartStore(state => state.items || []);
  const count = items.reduce((sum, item) => sum + item.quantity, 0);

  return (
    <TouchableOpacity onPress={() => navigation.navigate('Cart')} style={{ marginRight: 10, position: 'relative' }}>
      <Ionicons name="cart-outline" size={26} color="#fff" />
      {count > 0 && (
        <View style={{
          position: 'absolute', top: -4, right: -8, backgroundColor: '#dc3545',
          borderRadius: 10, minWidth: 18, height: 18, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 4
        }}>
          <Text style={{ color: '#fff', fontSize: 10, fontWeight: 'bold' }}>{count}</Text>
        </View>
      )}
    </TouchableOpacity>
  );
};

export default function ProductStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: '#D4AF37' },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: 'bold' },
      }}
    >
      <Stack.Screen 
        name="ProductList" 
        component={ProductListScreen} 
        options={({ navigation }) => ({ 
          title: 'Produk',
          headerRight: () => <CartButton navigation={navigation} />
        })} 
      />
      <Stack.Screen 
        name="ProductDetail" 
        component={ProductDetailScreen} 
        options={({ navigation }) => ({ 
          title: 'Detail Produk',
          headerRight: () => <CartButton navigation={navigation} />
        })} 
      />
      <Stack.Screen name="Cart" component={CartScreen} options={{ title: 'Keranjang' }} />
      <Stack.Screen name="Checkout" component={CheckoutScreen} options={{ title: 'Checkout' }} />
      <Stack.Screen name="TransactionList" component={TransactionListScreen} options={{ title: 'Riwayat Pesanan' }} />
      <Stack.Screen name="TransactionDetail" component={TransactionDetailScreen} options={{ title: 'Detail Pesanan' }} />
    </Stack.Navigator>
  );
}
